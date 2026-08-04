"""Telegram client, polling from inside the FastAPI event loop.

Not named telegram.py on purpose: python-telegram-bot installs a top-level
package called `telegram`, and a sibling module of that name would shadow it.

Every chat shares the "telegram" session — Jarvis is single-user, so splitting
per chat id would only fragment his own history.
"""

import asyncio
import io
import logging
import os
import re

from telegram import BotCommand, Update
from telegram.constants import ParseMode
from telegram.error import BadRequest, TelegramError
from telegram.ext import (
    AIORateLimiter,
    Application,
    CommandHandler,
    ContextTypes,
    MessageHandler,
    filters,
)

import brain
import memory
import voice

log = logging.getLogger(__name__)

TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "")
SESSION = "telegram"

# One conversation, one turn at a time. Telegram delivers updates concurrently,
# and because every Telegram chat shares the single "telegram" session, two
# racing messages would both read the same history and then both append to it —
# so the second reply would be blind to the first. It also stops a flood from
# fanning out into parallel paid OpenAI calls.
_turn = asyncio.Lock()

TRANSCRIBE_MODEL = os.environ.get("OPENAI_TRANSCRIBE_MODEL", "whisper-1")
# Jakub dictates in Czech. Telling Whisper up front is markedly more accurate
# than letting it guess, especially on short clips where there is little to
# detect from.
TRANSCRIBE_LANGUAGE = "cs"

_app: Application | None = None


async def _say(update: Update, text: str) -> None:
    """Deliver a reply as Markdown, tolerating anything Telegram throws.

    AIORateLimiter already retries a 429 for us; an error surfacing here means
    it exhausted those retries, and a dropped reply is not worth killing the
    handler over."""
    try:
        await update.message.reply_text(_markdown_safe(text), parse_mode=ParseMode.MARKDOWN)

        return
    except BadRequest:
        # Telegram rejects the whole message on unbalanced markup rather than
        # degrading it, and the model will eventually emit a stray * or `.
        # Resend raw: mangled formatting beats a silently lost answer.
        log.warning("markdown rejected, resending as plain text")
    except TelegramError:
        log.exception("could not deliver reply")

        return

    try:
        await update.message.reply_text(text)
    except TelegramError:
        log.exception("could not deliver reply")


_MARKUP = re.compile(r"[*`]+")
_CODE_SPAN = re.compile(r"`[^`]*`")
# An identifier the model left bare: two word-parts joined by an underscore.
_BARE_ID = re.compile(r"(?<![\w`*])(\w+_\w[\w.]*)(?![\w`*])")


def _markdown_safe(text: str) -> str:
    """Repair the two mistakes the model reliably makes about Telegram's legacy
    Markdown, which no amount of prompting reliably prevents:

    `**bold**` is CommonMark; Telegram wants `*bold*`. And a bare identifier
    like due_date reads as an italic marker — one unmatched underscore makes
    Telegram reject the whole message, so the reply arrives as raw text.

    Rewriting is deterministic and cheap; the model only has to get the meaning
    right, not the escaping.
    """
    text = re.sub(r"\*\*(.+?)\*\*", r"*\1*", text, flags=re.S)

    # Only outside existing code spans — what is already fenced is correct.
    out, last = [], 0

    for span in _CODE_SPAN.finditer(text):
        out.append(_BARE_ID.sub(r"`\1`", text[last : span.start()]))
        out.append(span.group())
        last = span.end()

    out.append(_BARE_ID.sub(r"`\1`", text[last:]))

    return "".join(out)


def _spoken(text: str) -> str:
    """Strip the Markdown before synthesis — it is for the eye, and ElevenLabs
    would otherwise read the asterisks out loud. Underscores become spaces so
    `add_task` is spoken as two words rather than spelled."""
    return _MARKUP.sub("", text).replace("• ", "").replace("_", " ")


async def _speak(update: Update, text: str) -> bool:
    """Send the reply as a voice note. Returns whether it actually landed, so
    the caller can fall back to text — in voice mode this is the only copy of
    the answer, and a failed synthesis would otherwise lose it silently."""
    audio = await voice.synthesize(_spoken(text), voice.OPUS)

    if audio is None:
        return False

    try:
        await update.message.reply_voice(audio)
    except TelegramError:
        log.exception("could not deliver voice note")

        return False

    return True


async def _answer(update: Update, text: str) -> None:
    """Run one message through the brain and reply. Shared by text and voice —
    a transcribed clip is just a text turn by the time it gets here."""
    try:
        await update.message.chat.send_action("typing")
    except TelegramError:
        # Purely cosmetic; never lose the answer over the typing indicator.
        pass

    async with _turn:
        try:
            reply = await brain.respond(SESSION, text)
        except Exception as error:
            log.exception("brain failed")
            reply = f"Something broke: {error}"

    reply = reply or "…"

    # Voice mode is exclusive: the note replaces the message rather than
    # accompanying it. Text remains the fallback, because a failed synthesis
    # must cost the delivery format, never the answer.
    if await memory.voice_mode(update.message.chat_id) and await _speak(update, reply):
        return

    await _say(update, reply)


HELP = """Jsem Jarvis, tvůj asistent v Pryvoře.

Piš mi normálně nebo pošli hlasovku — přepíšu si ji sám.

*Co umím*
• Založit úkol — „zavolat zubaři zítra, vysoká priorita"
• Uložit poznámku — „poznamenej si, že…"
• Říct, co tě dnes čeká — „co mám dneska?"
• Zapamatovat si, co ti mám pamatovat — „pamatuj si, že…"

*Příkazy*
/voice — odpovídat jen hlasem
/text — odpovídat jen textem (výchozí)
/reset — zapomenout tuhle konverzaci
/help — tahle nápověda

Dlouhodobou paměť /reset nemaže — jen vlákno konverzace."""


async def _on_help(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    await _say(update, HELP)


async def _on_reset(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    await memory.forget(SESSION)

    await _say(update, "Konverzaci jsem zapomněl. Dlouhodobá paměť zůstává.")


async def _on_voice_mode(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    await memory.set_voice_mode(update.message.chat_id, True)

    # Confirm by voice, since that is now the only channel — hearing it back is
    # the proof the mode actually works. If it cannot speak, say so in text
    # rather than leaving you to wonder why the bot went quiet.
    if not await _speak(update, "Od teď odpovídám jen hlasem."):
        await _say(update, "Hlasový režim zapnut, ale hlas se nepodařilo vygenerovat — píšu dál textem.")


async def _on_text_mode(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    await memory.set_voice_mode(update.message.chat_id, False)

    await _say(update, "Hlasové odpovědi vypnuty — jen text.")


async def _on_message(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    if not update.message or not update.message.text:
        return

    await _answer(update, update.message.text)


async def _on_voice(update: Update, _: ContextTypes.DEFAULT_TYPE) -> None:
    if not update.message or not update.message.voice:
        return

    await update.message.chat.send_action("typing")

    try:
        text = await _transcribe(update)
    except Exception as error:
        log.exception("transcription failed")
        await _say(update, f"Could not transcribe that: {error}")
        return

    if not text:
        await _say(update, "I could not make anything out of that.")
        return

    await _answer(update, text)


async def _transcribe(update: Update) -> str:
    """Telegram voice notes are OGG/Opus. The OpenAI client picks the format
    from the filename, so the buffer has to carry one — an unnamed BytesIO is
    rejected as an unrecognised file type."""
    file = await update.message.voice.get_file()
    audio = io.BytesIO(bytes(await file.download_as_bytearray()))
    audio.name = "voice.ogg"

    transcript = await brain.client().audio.transcriptions.create(
        model=TRANSCRIBE_MODEL,
        file=audio,
        language=TRANSCRIBE_LANGUAGE,
    )

    return transcript.text.strip()


async def _on_error(_: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Last line of defence. Anything reaching here is logged and dropped, so a
    single bad update can never take the poller — or the FastAPI app sharing
    this process — down with it."""
    log.error("unhandled telegram error", exc_info=context.error)


async def start() -> None:
    """Start polling. A missing token is not an error — the rest of the
    service runs fine without Telegram, so it just stays off."""
    global _app

    if not TOKEN:
        return

    # AIORateLimiter paces outbound calls to Telegram's documented limits and
    # retries a 429 rather than raising it at us. Without it a burst of replies
    # gets rejected outright.
    _app = Application.builder().token(TOKEN).rate_limiter(AIORateLimiter()).build()
    # Telegram sends /start by itself when a chat is first opened, so it is the
    # bot's front door — it shows the same help rather than nothing.
    _app.add_handler(CommandHandler(["start", "help"], _on_help))
    _app.add_handler(CommandHandler("reset", _on_reset))
    _app.add_handler(CommandHandler("voice", _on_voice_mode))
    _app.add_handler(CommandHandler("text", _on_text_mode))
    _app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, _on_message))
    _app.add_handler(MessageHandler(filters.VOICE, _on_voice))
    _app.add_error_handler(_on_error)

    await _app.initialize()
    await _app.start()

    try:
        # This is what fills the menu when you type "/" in Telegram. It is
        # published per bot, not per chat, and only refreshes when we send it —
        # so it has to be re-sent whenever this list changes.
        await _app.bot.set_my_commands([
            BotCommand("help", "Co všechno umím"),
            BotCommand("voice", "Odpovídat jen hlasem"),
            BotCommand("text", "Odpovídat jen textem"),
            BotCommand("reset", "Zapomenout tuhle konverzaci"),
        ])
    except TelegramError:
        # Only affects the command menu in the client, not the handlers.
        log.warning("could not publish the command list")

    await _app.updater.start_polling(drop_pending_updates=True)


async def stop() -> None:
    if _app is None:
        return

    await _app.updater.stop()
    await _app.stop()
    await _app.shutdown()
