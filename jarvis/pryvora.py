"""Client for Pryvora's internal API, plus the tool schemas the LLM sees.

Lives apart from main.py so brain.py can call these without importing the
FastAPI app — main.py imports brain, so the reverse would be circular.
"""

import os

import httpx

API_URL = os.environ.get("PRYVORA_API_URL", "http://backend:8000")
API_TOKEN = os.environ.get("JARVIS_API_TOKEN", "")


class PryvoraError(RuntimeError):
    """A non-2xx from Symfony, carrying its body so the model can correct itself."""


async def call(method: str, path: str, **kwargs) -> dict | list:
    if not API_TOKEN:
        raise PryvoraError("JARVIS_API_TOKEN is not set")

    async with httpx.AsyncClient(
        base_url=f"{API_URL}/api/jarvis",
        headers={"Authorization": f"Bearer {API_TOKEN}"},
        timeout=10.0,
    ) as client:
        response = await client.request(method, path, **kwargs)

    if response.is_error:
        raise PryvoraError(response.text)

    return response.json()


# ── tools ────────────────────────────────────────────────────────────────────

# Descriptions are written for the model, not for us: they say when to reach for
# a tool, since that is the part it gets wrong.
TOOLS = [
    {
        "type": "function",
        "function": {
            "name": "get_briefing",
            "description": (
                "Today's tasks, overdue tasks and the next 7 days of calendar "
                "events. Use for 'what's on today', 'what's coming up', or any "
                "question needing the current state of the day."
            ),
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_tasks",
            "description": "List tasks. Use when asked about tasks specifically rather than the whole day.",
            "parameters": {
                "type": "object",
                "properties": {
                    "filter": {
                        "type": "string",
                        "enum": ["today", "upcoming", "all"],
                        "description": "Which slice of the task list. Defaults to all.",
                    }
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "add_task",
            "description": "Create a task. Use whenever the user says they need to do something.",
            "parameters": {
                "type": "object",
                "properties": {
                    "title": {"type": "string"},
                    "description": {"type": "string"},
                    "priority": {"type": "string", "enum": ["low", "medium", "high"]},
                    "due_date": {
                        "type": "string",
                        "description": "Date only, YYYY-MM-DD. Resolve relative dates yourself.",
                    },
                },
                "required": ["title"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "add_note",
            "description": (
                "Save a note. Use for things to keep but not act on. Prefer "
                "add_task when there is something to actually do."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "title": {"type": "string", "description": "Optional; derived from the content when omitted."},
                    "content": {"type": "string"},
                },
                "required": ["content"],
            },
        },
    },
]


async def run_tool(name: str, args: dict) -> dict | list:
    if name == "get_briefing":
        return await call("GET", "/briefing")

    if name == "get_tasks":
        return await call("GET", "/tasks", params={"filter": args.get("filter", "all")})

    if name == "add_task":
        return await call("POST", "/tasks", json=args)

    if name == "add_note":
        return await call("POST", "/notes", json=args)

    raise PryvoraError(f"unknown tool: {name}")
