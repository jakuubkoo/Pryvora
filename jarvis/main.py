"""Jarvis — the assistant service.

Three clients, one brain: voice on a Raspberry Pi hits /chat/voice, the Pryvora
web widget hits /chat/text, and Telegram polls from inside this same process.
They keep separate short-term histories and share one long-term memory.

The /briefing, /tasks and /notes routes are the raw Pryvora passthrough, kept
for debugging the tools without going through the model.
"""

import base64
import os
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

import brain
import memory
import pryvora
import telegram_bot
import voice


@asynccontextmanager
async def lifespan(_: FastAPI):
    await telegram_bot.start()
    yield
    await telegram_bot.stop()


app = FastAPI(title="Jarvis", lifespan=lifespan)


class Message(BaseModel):
    message: str


async def _speak(text: str) -> str | None:
    """Base64 mp3 for the JSON response, or None when speech is unavailable."""
    audio = await voice.synthesize(text)

    return base64.b64encode(audio).decode() if audio else None


async def _chat(session: str, message: str) -> str:
    try:
        return await brain.respond(session, message)
    except Exception as error:
        raise HTTPException(503, str(error)) from error


# ── chat ─────────────────────────────────────────────────────────────────────


@app.post("/chat/text")
async def chat_text(body: Message) -> dict:
    return {"reply": await _chat("web", body.message)}


@app.post("/chat/voice")
async def chat_voice(body: Message) -> dict:
    reply = await _chat("voice", body.message)

    return {"reply": reply, "audio": await _speak(reply)}


@app.delete("/chat/{session}")
async def clear_history(session: str) -> dict:
    """Drop a client's short-term history. Long-term memory is untouched."""
    await memory.forget(session)

    return {"cleared": session}


# ── health and raw passthrough ───────────────────────────────────────────────


@app.get("/health")
async def health() -> dict:
    return {
        "status": "ok",
        "api": pryvora.API_URL,
        "token_configured": bool(pryvora.API_TOKEN),
        "openai_configured": bool(os.environ.get("OPENAI_API_KEY")),
        "voice_configured": bool(voice.API_KEY),
        "telegram_configured": bool(telegram_bot.TOKEN),
        "model": brain.MODEL,
    }


async def _proxy(method: str, path: str, **kwargs):
    try:
        return await pryvora.call(method, path, **kwargs)
    except pryvora.PryvoraError as error:
        raise HTTPException(502, str(error)) from error


@app.get("/briefing")
async def briefing() -> dict:
    return await _proxy("GET", "/briefing")


@app.get("/tasks")
async def tasks(filter: str = "all") -> list:
    return await _proxy("GET", "/tasks", params={"filter": filter})


@app.post("/tasks")
async def add_task(payload: dict) -> dict:
    return await _proxy("POST", "/tasks", json=payload)


@app.post("/notes")
async def add_note(payload: dict) -> dict:
    return await _proxy("POST", "/notes", json=payload)
