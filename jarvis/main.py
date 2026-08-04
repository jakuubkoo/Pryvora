"""Jarvis — the assistant service.

This is the transport layer only: a typed client over Pryvora's /api/jarvis
endpoints plus a health check. The assistant itself (LLM, tool loop, voice) is
not wired up yet; these functions are what it will call as tools.
"""

import os

import httpx
from fastapi import FastAPI, HTTPException

API_URL = os.environ.get("PRYVORA_API_URL", "http://backend:8000")
API_TOKEN = os.environ.get("JARVIS_API_TOKEN", "")

app = FastAPI(title="Jarvis")


def _client() -> httpx.AsyncClient:
    if not API_TOKEN:
        raise HTTPException(503, "JARVIS_API_TOKEN is not set")

    return httpx.AsyncClient(
        base_url=f"{API_URL}/api/jarvis",
        headers={"Authorization": f"Bearer {API_TOKEN}"},
        timeout=10.0,
    )


async def _call(method: str, path: str, **kwargs) -> dict | list:
    async with _client() as client:
        response = await client.request(method, path, **kwargs)

    if response.is_error:
        # Surface Pryvora's own error body — it carries the field-level
        # validation messages the assistant needs to correct itself.
        raise HTTPException(response.status_code, response.json())

    return response.json()


@app.get("/health")
async def health() -> dict:
    return {"status": "ok", "api": API_URL, "token_configured": bool(API_TOKEN)}


@app.get("/briefing")
async def briefing() -> dict:
    return await _call("GET", "/briefing")


@app.get("/tasks")
async def tasks(filter: str = "all") -> list:
    return await _call("GET", "/tasks", params={"filter": filter})


@app.post("/tasks")
async def add_task(payload: dict) -> dict:
    return await _call("POST", "/tasks", json=payload)


@app.post("/notes")
async def add_note(payload: dict) -> dict:
    return await _call("POST", "/notes", json=payload)
