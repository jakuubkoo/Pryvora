import { format } from 'date-fns'
import { CalendarPlus, MapPin } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { event_color, tint, events_for_day, format_event_range } from './event_utils'

/**
 * Everything on one day. Reached from a "+N more" cell, from a tap on a busy
 * day, and from Enter on a focused grid cell — which is what makes the events
 * inside a month cell reachable by keyboard as real buttons.
 */
export default function DayPeekDialog({
  open,
  on_open_change,
  day,
  events,
  on_open_event,
  on_create_on_day,
})
{
  if (!day)
  {
    return null
  }

  const day_events = events_for_day(events, day)

  return (
    <Dialog open={open} onOpenChange={on_open_change}>
      <DialogContent className="panel max-h-[80vh] w-[calc(100vw-2rem)] overflow-y-auto sm:max-w-[420px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[19px] font-bold leading-tight tracking-[-0.02em] text-ink">
            {format(day, 'EEEE d MMMM')}
          </DialogTitle>
          <DialogDescription className="num text-[12.5px] text-dim">
            {day_events.length} {day_events.length === 1 ? 'event' : 'events'}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-1.5">
          {day_events.map(event => {
            const color = event_color(event)

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
                  <span
                    className="flex max-w-[120px] flex-none items-center gap-1 rounded-pill px-2 py-1 text-[10.5px] font-semibold"
                    style={{ background: tint(color, 13), color }}
                  >
                    <MapPin className="h-2.5 w-2.5 flex-none"/>
                    <span className="truncate">{event.location}</span>
                  </span>
                )}
              </button>
            )
          })}
        </div>

        <button
          type="button"
          onClick={() => on_create_on_day(day)}
          className="flex items-center justify-center gap-1.5 rounded-[11px] border border-line px-3.5 py-2 text-[13px] font-semibold text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
        >
          <CalendarPlus className="h-3.5 w-3.5"/>
          New event on this day
        </button>
      </DialogContent>
    </Dialog>
  )
}
