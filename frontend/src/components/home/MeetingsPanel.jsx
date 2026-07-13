import { CalendarDays, AlertCircle } from 'lucide-react'
import { Panel, PanelHead, EmptyState } from './Panel'
import { tint } from './tint'

const RULE_COLORS = ['var(--accent)', 'var(--gold)', 'var(--clay)', 'var(--violet)', 'var(--sage)']

export default function MeetingsPanel({ events, loading, error, next_id })
{
  const render_body = () =>
  {
    if (loading)
    {
      return (
        <div className="flex flex-1 flex-col gap-2 pt-1">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-[46px] animate-pulse rounded-[11px] bg-line2"/>
          ))}
        </div>
      )
    }

    if (error)
    {
      return (
        <EmptyState icon={<AlertCircle className="h-[18px] w-[18px]" aria-hidden="true"/>}>
          Couldn&apos;t load your calendar. Try again shortly.
        </EmptyState>
      )
    }

    if (events.length === 0)
    {
      return (
        <EmptyState icon={<CalendarDays className="h-[18px] w-[18px]" aria-hidden="true"/>}>
          Nothing on the calendar today. Enjoy the quiet.
        </EmptyState>
      )
    }

    return (
      <div className="mt-3 flex flex-col gap-1.5">
        {events.map((event, index) => {
          const is_next = event.id === next_id
          const color = RULE_COLORS[index % RULE_COLORS.length]

          return (
            <div
              key={event.id}
              className="flex items-center gap-2.5 rounded-[11px] border border-transparent px-2 py-2"
              style={{
                background: is_next ? tint('var(--accent)', 8) : undefined,
                borderColor: is_next ? tint('var(--accent)', 30) : 'transparent',
                opacity: event.is_past ? 0.55 : 1,
              }}
            >
              <span className="num w-[52px] shrink-0 text-[12.5px] font-medium">
                {event.time_label}
              </span>

              <span
                className="h-[26px] w-[2px] shrink-0 rounded-full"
                style={{ background: color }}
                aria-hidden="true"
              />

              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-[13px] font-medium leading-tight">{event.title}</span>
                {event.location ? (
                  <span className="truncate text-[11.5px] leading-tight text-dim">{event.location}</span>
                ) : null}
              </span>

              {is_next ? (
                <span
                  className="shrink-0 rounded-[20px] px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-[0.12em] text-accent"
                  style={{ background: tint('var(--accent)', 13) }}
                >
                  Next
                </span>
              ) : null}
            </div>
          )
        })}
      </div>
    )
  }

  return (
    <Panel>
      <PanelHead label="Today's meetings">
        <span className="num text-[11.5px] text-faint">
          {loading || error ? '—' : `${events.length} today`}
        </span>
      </PanelHead>
      {render_body()}
    </Panel>
  )
}
