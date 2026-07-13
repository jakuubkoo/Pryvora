import { useEffect, useRef } from 'react'
import {
  differenceInMinutes,
  endOfDay,
  format,
  isSameDay,
  isToday,
  startOfDay,
} from 'date-fns'
import { cn } from '@/lib/utils'
import { week_window, event_color, tint, events_for_day, format_time } from './event_utils'

const HOUR_HEIGHT = 48
const DAY_MINUTES = 24 * 60

/**
 * Time-grid week view. Timed events are absolutely positioned by minute offset;
 * all-day (and multi-day) events get their own row above the grid, because they
 * have no meaningful position on a 24-hour axis.
 */
export default function WeekGrid({ cursor, events, on_open_event, on_create_on_day })
{
  const days = week_window(cursor)
  const scroll_ref = useRef(null)

  // Open on the working day rather than at midnight.
  useEffect(() =>
  {
    if (scroll_ref.current)
    {
      scroll_ref.current.scrollTop = 7 * HOUR_HEIGHT
    }
  }, [])

  const hours = Array.from({ length: 24 }, (_, i) => i)

  /** Clips an event to the given day, so a multi-day block renders per-column. */
  const slot_for = (event, day) =>
  {
    const day_start = startOfDay(day)
    const day_end = endOfDay(day)

    const start = event.starts_at < day_start ? day_start : event.starts_at
    const end = event.ends_at > day_end ? day_end : event.ends_at

    const top = (differenceInMinutes(start, day_start) / DAY_MINUTES) * (24 * HOUR_HEIGHT)
    const raw_height = (differenceInMinutes(end, start) / DAY_MINUTES) * (24 * HOUR_HEIGHT)

    // Never collapse to an invisible sliver.
    return { top, height: Math.max(raw_height, 20) }
  }

  return (
    <div className="panel overflow-hidden">
      {/* The 7-day time grid cannot compress below ~640px — let it scroll
          inside its own panel rather than overflowing the page. */}
      <div className="overflow-x-auto">
        <div className="min-w-[640px]">
          {/* Day header */}
          <div className="grid grid-cols-[52px_repeat(7,1fr)] border-b border-line">
            <div/>
            {days.map(day => (
              <div
                key={format(day, 'yyyy-MM-dd')}
                className="flex flex-col items-center gap-0.5 border-l border-line2 py-2.5"
              >
                <span className="eyebrow">{format(day, 'EEE')}</span>
                <span
                  className={cn(
                    'num grid h-7 w-7 place-items-center rounded-full text-[13px] font-semibold',
                    isToday(day) ? 'bg-accent font-bold text-on-accent' : 'text-ink'
                  )}
                >
                  {format(day, 'd')}
                </span>
              </div>
            ))}
          </div>

          {/* All-day row */}
          <div className="grid grid-cols-[52px_repeat(7,1fr)] border-b border-line">
            <div className="eyebrow flex items-start justify-end px-2 py-2">All day</div>
            {days.map(day => {
              const all_day_events = events_for_day(events, day).filter(event => event.all_day)

              return (
                <div
                  key={format(day, 'yyyy-MM-dd')}
                  className="flex min-h-[34px] flex-col gap-1 border-l border-line2 p-1"
                >
                  {all_day_events.map(event => (
                    <button
                      key={event.id}
                      type="button"
                      onClick={() => on_open_event(event)}
                      className="truncate rounded-[7px] px-1.5 py-1 text-left text-[11.5px] font-semibold transition-opacity hover:opacity-80"
                      style={{
                        background: tint(event_color(event.id), 13),
                        color: event_color(event.id),
                      }}
                    >
                      {event.title}
                    </button>
                  ))}
                </div>
              )
            })}
          </div>

          {/* Hour grid */}
          <div ref={scroll_ref} className="custom-scrollbar max-h-[560px] overflow-y-auto">
            <div className="relative grid grid-cols-[52px_repeat(7,1fr)]">
              {/* Hour gutter */}
              <div>
                {hours.map(hour => (
                  <div
                    key={hour}
                    className="relative border-t border-line2 first:border-t-0"
                    style={{ height: HOUR_HEIGHT }}
                  >
                    {hour > 0 && (
                      <span className="num absolute -top-[7px] right-2 text-[10.5px] text-faint">
                        {format(new Date(2000, 0, 1, hour), 'h a').toLowerCase()}
                      </span>
                    )}
                  </div>
                ))}
              </div>

              {days.map(day => {
                const timed = events_for_day(events, day).filter(event => !event.all_day)

                return (
                  <div key={format(day, 'yyyy-MM-dd')} className="relative border-l border-line2">
                    {hours.map(hour => (
                      <div
                        key={hour}
                        role="button"
                        tabIndex={-1}
                        aria-label={`Create event on ${format(day, 'EEEE d MMMM')} at ${hour}:00`}
                        onClick={() => on_create_on_day(new Date(
                          day.getFullYear(), day.getMonth(), day.getDate(), hour, 0
                        ))}
                        className="border-t border-line2 transition-colors first:border-t-0 hover:bg-panel-sunk"
                        style={{ height: HOUR_HEIGHT }}
                      />
                    ))}

                    {timed.map(event => {
                      const { top, height } = slot_for(event, day)
                      const color = event_color(event.id)

                      return (
                        <button
                          key={event.id}
                          type="button"
                          onClick={() => on_open_event(event)}
                          className="absolute left-0.5 right-0.5 flex flex-col items-start overflow-hidden rounded-[7px] border-l-2 px-1.5 py-1 text-left transition-opacity hover:opacity-85"
                          style={{
                            top,
                            height,
                            background: tint(color, 13),
                            borderLeftColor: color,
                            color,
                          }}
                        >
                          <span className="w-full truncate text-[11.5px] font-bold leading-tight">
                            {event.title}
                          </span>
                          {height > 32 && (
                            <span className="num truncate text-[10px] font-semibold opacity-80">
                              {format_time(event.starts_at)}
                            </span>
                          )}
                        </button>
                      )
                    })}

                    {/* Now-line */}
                    {isSameDay(day, new Date()) && <NowLine/>}
                  </div>
                )
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function NowLine()
{
  const now = new Date()
  const top = (differenceInMinutes(now, startOfDay(now)) / DAY_MINUTES) * (24 * HOUR_HEIGHT)

  return (
    <div
      className="pointer-events-none absolute left-0 right-0 z-10 flex items-center"
      style={{ top }}
      aria-hidden="true"
    >
      <span className="h-1.5 w-1.5 flex-none rounded-full bg-down"/>
      <span className="h-px flex-1 bg-down"/>
    </div>
  )
}
