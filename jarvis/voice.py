"""ElevenLabs speech synthesis, shared by the HTTP and Telegram clients.

Its own module because main.py imports telegram_bot, so telegram_bot importing
main would be circular.
"""

import logging
import os

import httpx

log = logging.getLogger(__name__)

API_KEY = os.environ.get("ELEVENLABS_API_KEY", "")
# Rachel — ElevenLabs' stock voice, so speech works before picking one.
VOICE_ID = os.environ.get("ELEVENLABS_VOICE_ID") or "21m00Tcm4TlvDq8ikWAM"
MODEL = os.environ.get("ELEVENLABS_MODEL", "eleven_flash_v2_5")

# Flash is quick to generate but also *talks* quickly: measured at 212 words per
# minute unaltered, against 172 for the multilingual model. 0.7 (the floor
# ElevenLabs accepts) came out at ~150 and read as sluggish, so 0.8 — Flash's
# speed belongs in the generation, not in the delivery, but not to the point of
# sounding laboured.
SPEED = float(os.environ.get("ELEVENLABS_SPEED", "0.8"))

# The Pi gets mp3 over JSON. Telegram voice notes are natively OGG/Opus, which
# renders as a real voice note rather than an audio file attachment.
MP3 = "mp3_44100_128"
OPUS = "opus_48000_64"


async def synthesize(text: str, output_format: str = MP3) -> bytes | None:
    """Speech for `text`, or None when it is unavailable.

    Never raises: a missing voice must cost the caller its audio, never the
    answer it was attached to.
    """
    if not API_KEY or not text.strip():
        return None

    try:
        async with httpx.AsyncClient(timeout=30.0) as client:
            response = await client.post(
                f"https://api.elevenlabs.io/v1/text-to-speech/{VOICE_ID}",
                headers={"xi-api-key": API_KEY},
                params={"output_format": output_format},
                json={
                    "text": text,
                    "model_id": MODEL,
                    "voice_settings": {"speed": SPEED},
                },
            )

        if response.is_error:
            log.warning("elevenlabs %s: %s", response.status_code, response.text[:200])
            return None

        return response.content
    except httpx.HTTPError:
        log.exception("elevenlabs request failed")
        return None
