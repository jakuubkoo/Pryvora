import { useState, useRef, useEffect } from 'react'
import { Sparkles, ArrowRight } from 'lucide-react'
import { vora_insights, vora_suggestions, vora_reply } from '@/lib/home_mock_data'

const CARD_BG = 'rgba(255,255,255,.06)'
const CARD_LINE = 'rgba(255,255,255,.09)'

export default function VoraPanel()
{
  const [messages, set_messages] = useState([])
  const [draft, set_draft] = useState('')
  const scroll_ref = useRef(null)

  useEffect(() =>
  {
    const node = scroll_ref.current
    if (node)
    {
      node.scrollTop = node.scrollHeight
    }
  }, [messages])

  const send = (text) =>
  {
    const trimmed = text.trim()
    if (!trimmed)
    {
      return
    }

    set_messages((prev) => [
      ...prev,
      { id: `${Date.now()}_u`, role: 'user', text: trimmed },
      { id: `${Date.now()}_v`, role: 'vora', text: vora_reply(trimmed) },
    ])
    set_draft('')
  }

  const handle_submit = (event) =>
  {
    event.preventDefault()
    send(draft)
  }

  return (
    <div className="flex h-full min-w-0 flex-col rounded-[18px] bg-vora-surface px-5 py-[18px] text-vora-ink">
      <div className="flex items-center gap-2">
        <span className="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-[7px] bg-accent">
          <Sparkles className="h-[13px] w-[13px] text-on-accent" aria-hidden="true"/>
        </span>
        <span className="text-[14px] font-semibold tracking-[-0.01em]">Vora</span>

        <span className="ml-auto flex items-center gap-1.5 text-[11px] text-vora-ink/55">
          <span
            className="h-[6px] w-[6px] rounded-full bg-accent"
            style={{ animation: 'pulse 2.2s infinite' }}
            aria-hidden="true"
          />
          Listening
        </span>
      </div>

      <div
        ref={scroll_ref}
        className="custom-scrollbar mt-4 flex max-h-[300px] min-h-[180px] flex-1 flex-col gap-2.5 overflow-y-auto pr-1"
      >
        {vora_insights.map((insight) => (
          <div
            key={insight.tag}
            className="rounded-[11px] border p-2.5"
            style={{ background: CARD_BG, borderColor: CARD_LINE }}
          >
            <span
              className="text-[9.5px] font-bold uppercase tracking-[0.12em]"
              style={{ color: insight.color }}
            >
              {insight.tag}
            </span>
            <p className="mt-1 text-[12.5px] leading-relaxed text-vora-ink/80">{insight.text}</p>
          </div>
        ))}

        {messages.map((message) => (
          <div
            key={message.id}
            className={
              message.role === 'user'
                ? 'ml-auto max-w-[85%] rounded-[11px] bg-accent px-2.5 py-2 text-[12.5px] leading-relaxed text-on-accent'
                : 'max-w-[92%] rounded-[11px] border px-2.5 py-2 text-[12.5px] leading-relaxed text-vora-ink/85'
            }
            style={
              message.role === 'user'
                ? undefined
                : { background: CARD_BG, borderColor: CARD_LINE }
            }
          >
            {message.text}
          </div>
        ))}
      </div>

      <div className="mt-3 flex flex-wrap gap-1.5">
        {vora_suggestions.map((suggestion) => (
          <button
            key={suggestion}
            type="button"
            onClick={() => send(suggestion)}
            className="rounded-[20px] border px-2.5 py-1 text-[11.5px] text-vora-ink/75 transition-colors hover:text-vora-ink"
            style={{ background: CARD_BG, borderColor: CARD_LINE }}
          >
            {suggestion}
          </button>
        ))}
      </div>

      <form onSubmit={handle_submit} className="mt-3 flex items-center gap-2">
        <input
          type="text"
          value={draft}
          onChange={(event) => set_draft(event.target.value)}
          placeholder="Ask Vora…"
          aria-label="Ask Vora"
          className="min-w-0 flex-1 rounded-[11px] border bg-transparent px-3 py-2 text-[13px] text-vora-ink placeholder:text-vora-ink/40 focus:outline-none focus-visible:outline-2"
          style={{ background: CARD_BG, borderColor: CARD_LINE }}
        />
        <button
          type="submit"
          aria-label="Send message to Vora"
          className="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[8px] bg-accent transition-opacity hover:opacity-90 disabled:opacity-40"
          disabled={!draft.trim()}
        >
          <ArrowRight className="h-[15px] w-[15px] text-on-accent" aria-hidden="true"/>
        </button>
      </form>
    </div>
  )
}
