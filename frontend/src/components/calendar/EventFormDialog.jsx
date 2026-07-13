import { useEffect, useState } from 'react'
import { format, parse, startOfDay, endOfDay, addHours } from 'date-fns'
import { Lock, AlertCircle } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import {
  reminder_presets,
  reminder_to_preset,
  preset_to_reminder,
  format_time,
} from './event_utils'

const field_class =
  'w-full rounded-[11px] border border-line bg-panel-sunk px-3 py-2 text-[13.5px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent'

const DATE_FMT = 'yyyy-MM-dd'
const TIME_FMT = 'HH:mm'

/** Builds the initial form state for either a new event or an edited one. */
const initial_state = (event, initial_date) =>
{
  if (event)
  {
    return {
      title: event.title,
      description: event.description,
      location: event.location,
      all_day: event.all_day,
      start_date: format(event.starts_at, DATE_FMT),
      start_time: format(event.starts_at, TIME_FMT),
      end_date: format(event.ends_at, DATE_FMT),
      end_time: format(event.ends_at, TIME_FMT),
      reminder_preset: reminder_to_preset(event.reminder_at, event.starts_at),
      custom_reminder: event.reminder_at,
    }
  }

  // A day was clicked: keep that date, but start at a sane hour rather than
  // midnight (the click carries no time of day).
  const base = initial_date || new Date()
  const start = base.getHours() === 0 && base.getMinutes() === 0
    ? new Date(base.getFullYear(), base.getMonth(), base.getDate(), 9, 0)
    : base
  const end = addHours(start, 1)

  return {
    title: '',
    description: '',
    location: '',
    all_day: false,
    start_date: format(start, DATE_FMT),
    start_time: format(start, TIME_FMT),
    end_date: format(end, DATE_FMT),
    end_time: format(end, TIME_FMT),
    reminder_preset: 'none',
    custom_reminder: null,
  }
}

const to_date = (date_str, time_str) =>
{
  const parsed = parse(`${date_str} ${time_str}`, `${DATE_FMT} ${TIME_FMT}`, new Date())
  return isNaN(parsed.getTime()) ? null : parsed
}

export default function EventFormDialog({
  open,
  on_open_change,
  event,
  initial_date,
  on_submit,
  saving,
  error,
})
{
  const is_edit = Boolean(event)
  const [form, set_form] = useState(() => initial_state(event, initial_date))
  const [validation, set_validation] = useState('')

  // Re-seed whenever the dialog is opened for a different event/day.
  useEffect(() =>
  {
    if (open)
    {
      set_form(initial_state(event, initial_date))
      set_validation('')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, event?.id, initial_date?.getTime()])

  const update = (patch) =>
  {
    set_form(current => ({ ...current, ...patch }))
  }

  const resolve_dates = (state) =>
  {
    if (state.all_day)
    {
      const start = to_date(state.start_date, '00:00')
      const end = to_date(state.end_date, '00:00')

      if (!start || !end)
      {
        return null
      }

      // The backend enforces startsAt < endsAt, so an all-day event has to
      // occupy the whole day rather than collapsing to a single instant.
      return { starts_at: startOfDay(start), ends_at: endOfDay(end) }
    }

    const start = to_date(state.start_date, state.start_time)
    const end = to_date(state.end_date, state.end_time)

    if (!start || !end)
    {
      return null
    }

    return { starts_at: start, ends_at: end }
  }

  const handle_submit = (e) =>
  {
    e.preventDefault()

    if (form.title.trim().length < 3)
    {
      set_validation('Title must be at least 3 characters.')
      return
    }

    const dates = resolve_dates(form)

    if (!dates)
    {
      set_validation('Please provide a valid start and end date.')
      return
    }

    // Guard the backend's own rule here so it surfaces as a field message
    // instead of a 400.
    if (dates.starts_at >= dates.ends_at)
    {
      set_validation('The end time must be after the start time.')
      return
    }

    set_validation('')

    on_submit({
      title: form.title,
      description: form.description,
      location: form.location,
      all_day: form.all_day,
      starts_at: dates.starts_at,
      ends_at: dates.ends_at,
      reminder_at: preset_to_reminder(
        form.reminder_preset,
        dates.starts_at,
        form.custom_reminder
      ),
    })
  }

  const message = validation || error

  return (
    <Dialog open={open} onOpenChange={on_open_change}>
      <DialogContent className="panel max-h-[90vh] w-[calc(100vw-2rem)] overflow-y-auto sm:max-w-[520px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[17px] font-bold tracking-[-0.015em] text-ink">
            {is_edit ? 'Edit event' : 'New event'}
          </DialogTitle>
          <DialogDescription className="text-[12.5px] text-dim">
            {is_edit
              ? 'Update the details of this event.'
              : 'Add something to your calendar.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handle_submit} className="flex flex-col gap-4">
          {message && (
            <div
              role="alert"
              className="flex items-start gap-2 rounded-[11px] px-3 py-2.5 text-[12.5px] font-medium text-down"
              style={{ background: 'color-mix(in srgb, var(--down) 11%, transparent)' }}
            >
              <AlertCircle className="mt-px h-3.5 w-3.5 flex-none"/>
              <span>{message}</span>
            </div>
          )}

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="event-title" className="text-[12.5px] font-semibold text-ink">
              Title
            </Label>
            <input
              id="event-title"
              value={form.title}
              onChange={(e) => update({ title: e.target.value })}
              placeholder="Dentist, standup, dinner with Sam…"
              className={field_class}
              autoFocus
              required
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between">
              <Label htmlFor="event-description" className="text-[12.5px] font-semibold text-ink">
                Description
              </Label>
              {/* The title is stored in plaintext; the description is encrypted
                  at rest. Saying so is the honest thing to do. */}
              <span
                className="flex items-center gap-1 rounded-pill px-2 py-0.5 text-[10.5px] font-semibold text-accent"
                style={{ background: 'color-mix(in srgb, var(--accent) 13%, transparent)' }}
              >
                <Lock className="h-2.5 w-2.5"/>
                Encrypted
              </span>
            </div>
            <textarea
              id="event-description"
              value={form.description}
              onChange={(e) => update({ description: e.target.value })}
              placeholder="Only you can read this."
              rows={3}
              className={cn(field_class, 'resize-none')}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="event-location" className="text-[12.5px] font-semibold text-ink">
              Location
            </Label>
            <input
              id="event-location"
              value={form.location}
              onChange={(e) => update({ location: e.target.value })}
              placeholder="Optional"
              className={field_class}
            />
          </div>

          {/* All-day toggle */}
          <label className="flex cursor-pointer items-center justify-between rounded-[11px] border border-line px-3 py-2.5">
            <span className="text-[12.5px] font-semibold text-ink">All day</span>
            <input
              type="checkbox"
              checked={form.all_day}
              onChange={(e) => update({ all_day: e.target.checked })}
              className="h-4 w-4 cursor-pointer accent-[var(--accent)]"
            />
          </label>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="event-start-date" className="text-[12.5px] font-semibold text-ink">
                Starts
              </Label>
              <input
                id="event-start-date"
                type="date"
                value={form.start_date}
                onChange={(e) => update({ start_date: e.target.value })}
                className={cn(field_class, 'num')}
                required
              />
              {!form.all_day && (
                <input
                  type="time"
                  aria-label="Start time"
                  value={form.start_time}
                  onChange={(e) => update({ start_time: e.target.value })}
                  className={cn(field_class, 'num')}
                  required
                />
              )}
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="event-end-date" className="text-[12.5px] font-semibold text-ink">
                Ends
              </Label>
              <input
                id="event-end-date"
                type="date"
                value={form.end_date}
                onChange={(e) => update({ end_date: e.target.value })}
                className={cn(field_class, 'num')}
                required
              />
              {!form.all_day && (
                <input
                  type="time"
                  aria-label="End time"
                  value={form.end_time}
                  onChange={(e) => update({ end_time: e.target.value })}
                  className={cn(field_class, 'num')}
                  required
                />
              )}
            </div>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="event-reminder" className="text-[12.5px] font-semibold text-ink">
              Reminder
            </Label>
            <select
              id="event-reminder"
              value={form.reminder_preset}
              onChange={(e) => update({ reminder_preset: e.target.value })}
              className={cn(field_class, 'cursor-pointer')}
            >
              {reminder_presets.map(preset => (
                <option key={preset.value} value={preset.value}>
                  {preset.label}
                </option>
              ))}
              {/* Only offered when the stored reminder matches no preset, so
                  editing an event never silently rewrites it. */}
              {form.reminder_preset === 'custom' && form.custom_reminder && (
                <option value="custom">
                  {`Custom — ${format(form.custom_reminder, 'd MMM')}, ${format_time(form.custom_reminder)}`}
                </option>
              )}
            </select>
          </div>

          <DialogFooter className="gap-2 sm:gap-2">
            <button
              type="button"
              onClick={() => on_open_change(false)}
              disabled={saving}
              className="rounded-[11px] px-3.5 py-2 text-[13px] font-semibold text-dim transition-colors hover:bg-panel-sunk hover:text-ink disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving}
              className="rounded-[11px] bg-accent px-3.5 py-2 text-[13px] font-bold text-on-accent transition-opacity hover:opacity-90 disabled:opacity-50"
            >
              {saving ? 'Saving…' : is_edit ? 'Save changes' : 'Create event'}
            </button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
