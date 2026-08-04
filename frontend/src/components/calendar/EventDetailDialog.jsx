import { useState } from 'react'
import { format } from 'date-fns'
import { Clock, MapPin, Bell, Lock, Pencil, Trash2, AlertCircle } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { event_color, tint, format_event_range, format_time, source_label } from './event_utils'

const icon_class = 'mt-px h-3.5 w-3.5 flex-none text-faint'

function Row({ icon, children })
{
  return (
    <div className="flex items-start gap-2.5 border-b border-line2 py-2.5 last:border-b-0">
      {icon}
      <div className="min-w-0 flex-1 text-[13px] text-ink">{children}</div>
    </div>
  )
}

export default function EventDetailDialog({
  open,
  on_open_change,
  event,
  on_edit,
  on_delete,
  deleting,
  error,
})
{
  // Confirmation lives inline rather than in a second Dialog — nesting Radix
  // dialogs stacks two focus traps, and this keeps one clean keyboard path.
  const [confirming, set_confirming] = useState(false)
  const [seen_id, set_seen_id] = useState(event?.id)

  // Reset the confirm step when a different event is shown. Adjusting state
  // during render (rather than in an effect) is the supported pattern and
  // avoids the cascading re-render an effect would cause.
  if (event?.id !== seen_id)
  {
    set_seen_id(event?.id)
    set_confirming(false)
  }

  // Closing always clears the confirm step, so reopening never lands mid-delete.
  const handle_open_change = (next) =>
  {
    if (!next)
    {
      set_confirming(false)
    }
    on_open_change(next)
  }

  if (!event)
  {
    return null
  }

  const color = event_color(event)

  return (
    <Dialog open={open} onOpenChange={handle_open_change}>
      <DialogContent className="panel w-[calc(100vw-2rem)] sm:max-w-[460px]">
        <DialogHeader className="text-left">
          <div className="mb-1 flex flex-wrap items-center gap-1.5">
            <span
              className="w-fit rounded-pill px-2.5 py-1 text-[11px] font-bold"
              style={{ background: tint(color, 13), color }}
            >
              {format(event.starts_at, 'EEEE d MMMM')}
            </span>
            {event.source && (
              <span className="w-fit rounded-pill px-2.5 py-1 text-[11px] font-bold text-faint ring-1 ring-inset ring-line">
                {source_label(event)}
              </span>
            )}
          </div>
          <DialogTitle className="text-[19px] font-bold leading-tight tracking-[-0.02em] text-ink">
            {event.title}
          </DialogTitle>
          <DialogDescription className="sr-only">Event details</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col">
          <Row icon={<Clock className={icon_class} aria-hidden="true"/>}>
            <span className="num">{format_event_range(event)}</span>
          </Row>

          {event.location && (
            <Row icon={<MapPin className={icon_class} aria-hidden="true"/>}>
              {event.location}
            </Row>
          )}

          {event.reminder_at && (
            <Row icon={<Bell className={icon_class} aria-hidden="true"/>}>
              <span className="num">
                {`${format(event.reminder_at, 'd MMM')}, ${format_time(event.reminder_at)}`}
              </span>
            </Row>
          )}

          <Row icon={<Lock className={icon_class} aria-hidden="true"/>}>
            <div className="flex flex-col gap-1">
              <span className="eyebrow">Description — encrypted at rest</span>
              {event.description
                ? (
                  <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-ink">
                    {event.description}
                  </p>
                )
                : (
                  <p className="text-[13px] text-faint">No description.</p>
                )}
            </div>
          </Row>
        </div>

        {error && (
          <div
            role="alert"
            className="flex items-start gap-2 rounded-[11px] px-3 py-2.5 text-[12.5px] font-medium text-down"
            style={{ background: 'color-mix(in srgb, var(--down) 11%, transparent)' }}
          >
            <AlertCircle className="mt-px h-3.5 w-3.5 flex-none"/>
            <span>{error}</span>
          </div>
        )}

        {confirming
          ? (
            <div
              className="flex flex-col gap-3 rounded-[11px] px-3 py-3"
              style={{ background: 'color-mix(in srgb, var(--down) 8%, transparent)' }}
            >
              <p className="text-[12.5px] font-medium text-ink">
                Delete “{event.title}”? This cannot be undone.
              </p>
              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => set_confirming(false)}
                  disabled={deleting}
                  className="rounded-[11px] px-3 py-1.5 text-[12.5px] font-semibold text-dim transition-colors hover:text-ink disabled:opacity-50"
                >
                  Keep
                </button>
                <button
                  type="button"
                  onClick={() => on_delete(event)}
                  disabled={deleting}
                  autoFocus
                  className="rounded-[11px] bg-down px-3 py-1.5 text-[12.5px] font-bold text-on-accent transition-opacity hover:opacity-90 disabled:opacity-50"
                >
                  {deleting ? 'Deleting…' : 'Delete event'}
                </button>
              </div>
            </div>
          )
          : (
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => set_confirming(true)}
                className="flex items-center gap-1.5 rounded-[11px] px-3 py-2 text-[13px] font-semibold text-dim transition-colors hover:bg-panel-sunk hover:text-down"
              >
                <Trash2 className="h-3.5 w-3.5"/>
                Delete
              </button>
              <button
                type="button"
                onClick={() => on_edit(event)}
                className="flex items-center gap-1.5 rounded-[11px] bg-accent px-3.5 py-2 text-[13px] font-bold text-on-accent transition-opacity hover:opacity-90"
              >
                <Pencil className="h-3.5 w-3.5"/>
                Edit
              </button>
            </div>
          )}
      </DialogContent>
    </Dialog>
  )
}
