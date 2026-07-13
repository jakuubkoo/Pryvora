import { useEffect, useRef, useState } from 'react'
import {
  addDays,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  isToday,
  startOfMonth,
  startOfWeek,
} from 'date-fns'
import { cn } from '@/lib/utils'
import EventChip from './EventChip'
import { WEEK_STARTS_ON, month_window, events_for_day, event_color } from './event_utils'

const MAX_CHIPS = 3

const key_of = (day) => format(day, 'yyyy-MM-dd')

export default function MonthGrid({
  cursor,
  events,
  on_open_event,
  on_open_day,
  on_create_on_day,
})
{
  const days = month_window(cursor)
  const weeks = []

  for (let i = 0; i < days.length; i += 7)
  {
    weeks.push(days.slice(i, i + 7))
  }

  const weekday_labels = days.slice(0, 7)

  // Roving tabindex: the grid is one tab stop, arrows move between cells.
  const [focused_date, set_focused_date] = useState(() =>
  {
    const today = days.find(day => isToday(day))
    return today || startOfMonth(cursor)
  })

  const cell_refs = useRef({})
  const should_focus = useRef(false)

  // A new month means the old focus target is gone — re-seat it, but never
  // steal focus from elsewhere on the page.
  useEffect(() =>
  {
    const window_days = month_window(cursor)
    const still_visible = window_days.some(day => isSameDay(day, focused_date))

    if (!still_visible)
    {
      const today = window_days.find(day => isToday(day))
      set_focused_date(today || startOfMonth(cursor))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cursor])

  useEffect(() =>
  {
    if (!should_focus.current)
    {
      return
    }

    should_focus.current = false
    cell_refs.current[key_of(focused_date)]?.focus()
  }, [focused_date])

  const move_focus = (next_day) =>
  {
    // Clamp to the visible window so focus can never land on an unrendered cell.
    const inside = days.some(day => isSameDay(day, next_day))

    if (!inside)
    {
      return
    }

    should_focus.current = true
    set_focused_date(next_day)
  }

  const handle_key_down = (event, day) =>
  {
    const day_events = events_for_day(events, day)

    switch (event.key)
    {
      case 'ArrowLeft':
        event.preventDefault()
        move_focus(addDays(day, -1))
        break
      case 'ArrowRight':
        event.preventDefault()
        move_focus(addDays(day, 1))
        break
      case 'ArrowUp':
        event.preventDefault()
        move_focus(addDays(day, -7))
        break
      case 'ArrowDown':
        event.preventDefault()
        move_focus(addDays(day, 7))
        break
      case 'Home':
        event.preventDefault()
        move_focus(startOfWeek(day, { weekStartsOn: WEEK_STARTS_ON }))
        break
      case 'End':
        event.preventDefault()
        move_focus(endOfWeek(day, { weekStartsOn: WEEK_STARTS_ON }))
        break
      case 'Enter':
      case ' ':
        event.preventDefault()
        // An empty day goes straight to create; a busy day opens the day peek,
        // which is where its events become real, focusable buttons.
        if (day_events.length > 0)
        {
          on_open_day(day)
        }
        else
        {
          on_create_on_day(day)
        }
        break
      default:
        break
    }
  }

  return (
    <div className="panel overflow-hidden">
      <div role="grid" aria-label={`${format(cursor, 'MMMM yyyy')} calendar`}>
        {/* Weekday header */}
        <div role="row" className="grid grid-cols-7 border-b border-line">
          {weekday_labels.map(day => (
            <div
              key={format(day, 'EEE')}
              role="columnheader"
              aria-label={format(day, 'EEEE')}
              className="eyebrow px-2 py-2.5 text-center sm:text-left sm:px-3"
            >
              <span className="sm:hidden">{format(day, 'EEEEE')}</span>
              <span className="hidden sm:inline">{format(day, 'EEE')}</span>
            </div>
          ))}
        </div>

        {weeks.map((week, week_index) => (
          <div
            key={key_of(week[0])}
            role="row"
            className={cn('grid grid-cols-7', week_index > 0 && 'border-t border-line2')}
          >
            {week.map(day => {
              const day_events = events_for_day(events, day)
              const visible = day_events.slice(0, MAX_CHIPS)
              const overflow = day_events.length - visible.length
              const in_month = isSameMonth(day, cursor)
              const is_today = isToday(day)
              const is_focused = isSameDay(day, focused_date)

              return (
                <div
                  key={key_of(day)}
                  role="gridcell"
                  tabIndex={is_focused ? 0 : -1}
                  ref={(node) => { cell_refs.current[key_of(day)] = node }}
                  aria-label={
                    `${format(day, 'EEEE d MMMM yyyy')}` +
                    `${is_today ? ', today' : ''}` +
                    `, ${day_events.length === 0
                      ? 'no events'
                      : `${day_events.length} event${day_events.length === 1 ? '' : 's'}`}`
                  }
                  aria-selected={is_focused}
                  onKeyDown={(e) => handle_key_down(e, day)}
                  onClick={() =>
                  {
                    if (day_events.length > 0)
                    {
                      on_open_day(day)
                    }
                    else
                    {
                      on_create_on_day(day)
                    }
                  }}
                  className={cn(
                    'group relative flex min-h-[86px] cursor-pointer flex-col gap-1 border-l border-line2 p-1.5 text-left transition-colors first:border-l-0 sm:min-h-[118px] sm:p-2',
                    !in_month && 'bg-panel-sunk/40',
                    'hover:bg-panel-sunk'
                  )}
                >
                  <div className="flex items-center justify-between">
                    <span
                      className={cn(
                        'num grid h-6 w-6 place-items-center rounded-full text-[12.5px] font-semibold',
                        is_today && 'bg-accent font-bold text-on-accent',
                        !is_today && in_month && 'text-ink',
                        !is_today && !in_month && 'text-faint'
                      )}
                    >
                      {format(day, 'd')}
                    </span>
                  </div>

                  {/* sm+ : real chips */}
                  <div className="hidden min-w-0 flex-col gap-1 sm:flex">
                    {visible.map(event => (
                      <EventChip
                        key={event.id}
                        event={event}
                        on_open={on_open_event}
                      />
                    ))}

                    {overflow > 0 && (
                      <span className="num px-1.5 text-[10.5px] font-semibold text-dim">
                        +{overflow} more
                      </span>
                    )}
                  </div>

                  {/* below sm : dots — a 7-column grid has no room for text */}
                  <div className="flex flex-wrap gap-1 sm:hidden">
                    {day_events.slice(0, 4).map(event => (
                      <span
                        key={event.id}
                        className="h-1.5 w-1.5 rounded-full"
                        style={{ background: event_color(event.id) }}
                        aria-hidden="true"
                      />
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        ))}
      </div>
    </div>
  )
}
