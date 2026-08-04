"""What Jarvis knows: short-term history in Redis, long-term facts on disk.

The LLM is stateless — it keeps nothing between calls — so both layers are
assembled here and handed to it fresh every turn.

Short-term is per client session, so voice, web and Telegram don't interleave.
Long-term is one shared set of files, which is what lets Jarvis recognise you
whichever client you speak from.
"""

import json
import os
from datetime import datetime, timezone
from pathlib import Path

import redis.asyncio as redis

MEMORY_DIR = Path(__file__).parent / "memory"
# The memory files are gitignored — they are Jakub's, not the repo's — so a
# fresh clone has no directory to write into. Jarvis then simply starts with no
# long-term memory and fills it as it learns.
MEMORY_DIR.mkdir(parents=True, exist_ok=True)
REDIS_URL = os.environ.get("REDIS_URL", "redis://redis:6379/0")

# Enough to hold a conversation, short enough that the prompt stays cheap and
# the key can never grow without bound. Trimmed on write, so no cleanup job.
HISTORY_LIMIT = 20

# The model picks this argument, so it is a whitelist rather than a path join:
# remember(file="../../backend/.env") must not be able to write Symfony config.
WRITABLE = {"user", "preferences", "projects"}

# Loaded into every prompt. log.md is deliberately absent — it is append-only
# and unbounded, and would eventually eat the whole context window. Recency is
# already covered by the Redis history.
INJECTED = ("user", "preferences", "projects")

_pool: redis.Redis | None = None


def _client() -> redis.Redis:
    global _pool

    if _pool is None:
        _pool = redis.from_url(REDIS_URL, decode_responses=True)

    return _pool


# ── short-term ───────────────────────────────────────────────────────────────


async def history(session: str) -> list[dict]:
    """Recent turns for one client, oldest first, in OpenAI message shape."""
    try:
        raw = await _client().lrange(f"jarvis:history:{session}", 0, -1)
    except redis.RedisError:
        # A dead Redis costs us continuity, not the conversation.
        return []

    return [json.loads(item) for item in raw]


async def remember_turn(session: str, role: str, content: str) -> None:
    key = f"jarvis:history:{session}"

    try:
        async with _client().pipeline() as pipe:
            pipe.rpush(key, json.dumps({"role": role, "content": content}))
            pipe.ltrim(key, -HISTORY_LIMIT, -1)
            await pipe.execute()
    except redis.RedisError:
        pass


async def voice_mode(chat_id: int) -> bool:
    """Whether this chat wants spoken replies. A UI preference, not a fact
    about Jakub — so Redis, not the memory files."""
    try:
        return await _client().get(f"jarvis:voice_mode:{chat_id}") == "1"
    except redis.RedisError:
        return False


async def set_voice_mode(chat_id: int, on: bool) -> None:
    try:
        await _client().set(f"jarvis:voice_mode:{chat_id}", "1" if on else "0")
    except redis.RedisError:
        pass


async def forget(session: str) -> None:
    try:
        await _client().delete(f"jarvis:history:{session}")
    except redis.RedisError:
        pass


# ── long-term ────────────────────────────────────────────────────────────────


def _path(name: str) -> Path:
    if name not in WRITABLE:
        raise ValueError(f"not a writable memory file: {name!r}")

    return MEMORY_DIR / f"{name}.md"


def load() -> str:
    """The long-term files as one block for the system prompt."""
    sections = []

    for name in INJECTED:
        path = MEMORY_DIR / f"{name}.md"

        if path.exists():
            text = path.read_text(encoding="utf-8").strip()

            if text:
                sections.append(text)

    return "\n\n".join(sections)


def append_fact(name: str, fact: str) -> str:
    """Append one durable fact. Never rewrites — a bad write must not be able
    to destroy a good fact, and these writes are chosen by a language model."""
    fact = fact.strip().lstrip("-").strip()

    if not fact:
        raise ValueError("fact is empty")

    path = _path(name)
    existing = path.read_text(encoding="utf-8") if path.exists() else ""

    if fact.lower() in existing.lower():
        return f"already known, not duplicated: {fact}"

    with path.open("a", encoding="utf-8") as handle:
        handle.write(f"- {fact}\n")

    return f"saved to {name}.md: {fact}"


def append_log(session: str, role: str, content: str) -> None:
    stamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M")
    line = content.replace("\n", " ").strip()

    with (MEMORY_DIR / "log.md").open("a", encoding="utf-8") as handle:
        handle.write(f"\n- {stamp} [{session}] {role}: {line}")
