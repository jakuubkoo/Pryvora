import { format, isToday, isTomorrow } from 'date-fns'
import { MapPin, CalendarPlus } from 'lucide-react'
import { cn } from '@/lib/utils'
import EmptyMonth from './EmptyMonth'
import { event_color, tint, group_by_day, format_event_range } from './event_utils'

const day_label = (day) =>
{
  if (isToday(day))
  {
    return 'Today'
  }
  if (isTomorrow(day))
  {
    return 'Tomorrow'
  }
  return format(day, 'EEEE')
}

/**
 * The chronological read of the same data. Doubles as the comfortable surface
 * on narrow screens, where a 7-column grid can only afford dots.
 */
export default function AgendaList({ days, events, on_open_event, on_create, period_label })
{
  const groups = group_by_day(events, days)

  if (groups.length === 0)
  {
    return <EmptyMonth period_label={period_label} on_create={on_create}/>
  }

  return (
    <div className="panel divide-y divide-line2">
      {groups.map(({ day, events: day_events }) => (
        <div key={format(day, 'yyyy-MM-dd')} className="flex gap-4 px-5 py-[18px] sm:gap-6">
          {/* Date rail */}
          <div className="flex w-[58px] flex-none flex-col items-center pt-0.5 sm:w-[64px]">
            <span className="eyebrow">{format(day, 'MMM')}</span>
            <span
              className={cn(
                'num grid h-9 w-9 place-items-center rounded-full text-[17px] font-bold',
                isToday(day) ? 'bg-accent text-on-accent' : 'text-ink'
              )}
            >
              {format(day, 'd')}
            </span>
            <span className="mt-0.5 text-[10.5px] font-semibold text-faint">
              {day_label(day)}
            </span>
          </div>

          {/* Events */}
          <div className="flex min-w-0 flex-1 flex-col gap-1.5">
            {day_events.map(event => {
              const color = event_color(event.id)

              return (
                <button
                  key={event.id}
                  type="button"
                  onClick={() => on_open_event(event)}
                  className="flex min-w-0 items-center gap-3 rounded-[11px] border border-line2 px-3 py-2.5 text-left transition-colors hover:bg-panel-sunk"
                >
                  <span
                    className="h-8 w-1 flex-none rounded-full"
                    style={{ background: color }}
                    aria-hidden="true"
                  />

                  <span className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate text-[13.5px] font-semibold text-ink">
                      {event.title}
                    </span>
                    <span className="num truncate text-[11.5px] text-dim">
                      {format_event_range(event)}
                    </span>
                  </span>

                  {event.location && (
                    <span className="hidden max-w-[180px] items-center gap-1.5 truncate rounded-pill px-2.5 py-1 text-[11px] font-semibold sm:flex"
                      style={{ background: tint(color, 13), color }}
                    >
                      <MapPin className="h-3 w-3 flex-none"/>
                      <span className="truncate">{event.location}</span>
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        </div>
      ))}

      <div className="px-5 py-3">
        <button
          type="button"
          onClick={() => on_create()}
          className="flex items-center gap-1.5 text-[12.5px] font-semibold text-dim transition-colors hover:text-ink"
        >
          <CalendarPlus className="h-3.5 w-3.5"/>
          New event
        </button>
      </div>
    </div>
  )
}
