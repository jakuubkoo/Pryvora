"""The assistant itself: prompt assembly, the OpenAI call, and the tool loop."""

import json
import os
from datetime import date

from openai import AsyncOpenAI

import memory
import pryvora

MODEL = os.environ.get("OPENAI_MODEL", "gpt-5-mini-2025-08-07")

PERSONA = """You are Jarvis, Jakub's personal assistant inside Pryvora.

You speak with him through three clients — voice on a Raspberry Pi, the Pryvora
web app, and Telegram. They share one memory, so refer to earlier context freely
regardless of where it was said.

Be brief. Answer in one or two sentences unless asked for detail; you are often
being read aloud. Never invent tasks, events or facts — if you need the real
state of his day, call a tool.

When he tells you something durable about himself, his preferences or his
projects, call `remember`. Save the fact, not the sentence: "Uses Neovim", not
"He said he switched to Neovim yesterday". Do not save transient things like his
current mood or what he is doing right this minute."""

# A confused model can otherwise ping-pong tool calls forever, and each lap is
# a paid round trip.
MAX_TOOL_LAPS = 5

REMEMBER_TOOL = {
    "type": "function",
    "function": {
        "name": "remember",
        "description": (
            "Save a durable fact to long-term memory, available from every "
            "client from now on. Use for lasting facts, preferences and project "
            "context — not for transient state."
        ),
        "parameters": {
            "type": "object",
            "properties": {
                "file": {
                    "type": "string",
                    "enum": sorted(memory.WRITABLE),
                    "description": "user = personal facts, preferences = how he likes things, projects = what he builds",
                },
                "fact": {"type": "string", "description": "One short line, stated as a fact."},
            },
            "required": ["file", "fact"],
        },
    },
}

TOOLS = pryvora.TOOLS + [REMEMBER_TOOL]

_client: AsyncOpenAI | None = None


def client() -> AsyncOpenAI:
    global _client

    if _client is None:
        if not os.environ.get("OPENAI_API_KEY"):
            raise RuntimeError("OPENAI_API_KEY is not set")

        _client = AsyncOpenAI()

    return _client


def system_prompt() -> str:
    facts = memory.load()

    return "\n\n".join(
        part
        for part in (
            PERSONA,
            f"Today is {date.today().isoformat()}.",
            f"What you know about Jakub:\n\n{facts}" if facts else "",
        )
        if part
    )


async def _dispatch(name: str, args: dict) -> str:
    """Run one tool call and return a string result for the model."""
    try:
        if name == "remember":
            return memory.append_fact(args["file"], args["fact"])

        return json.dumps(await pryvora.run_tool(name, args), ensure_ascii=False)
    except Exception as error:
        # Handed back rather than raised: a 422 from Symfony tells the model
        # what it got wrong, and it can usually fix the call itself.
        return f"error: {error}"


async def respond(session: str, message: str) -> str:
    messages = [
        {"role": "system", "content": system_prompt()},
        *await memory.history(session),
        {"role": "user", "content": message},
    ]

    memory.append_log(session, "user", message)

    reply = ""

    for _ in range(MAX_TOOL_LAPS):
        completion = await client().chat.completions.create(
            model=MODEL,
            messages=messages,
            tools=TOOLS,
        )
        choice = completion.choices[0].message
        messages.append(choice)

        if not choice.tool_calls:
            reply = choice.content or ""
            break

        for call in choice.tool_calls:
            try:
                args = json.loads(call.function.arguments or "{}")
            except json.JSONDecodeError:
                args = {}

            messages.append({
                "role": "tool",
                "tool_call_id": call.id,
                "content": await _dispatch(call.function.name, args),
            })
    else:
        reply = "I got stuck working that out — try asking a different way."

    await memory.remember_turn(session, "user", message)
    await memory.remember_turn(session, "assistant", reply)
    memory.append_log(session, "jarvis", reply)

    return reply
