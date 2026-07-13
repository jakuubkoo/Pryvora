import {
  format,
  parseISO,
  startOfDay,
  endOfDay,
  startOfWeek,
  endOfWeek,
  startOfMonth,
  endOfMonth,
  eachDayOfInterval,
  addDays,
  isSameDay,
  isBefore,
  isAfter,
  differenceInMinutes,
} from 'date-fns'

/**
 * The single knob for the first column of the month grid.
 * 1 = Monday (ISO 8601). Change here and the grid + week view follow.
 */
export const WEEK_STARTS_ON = 1

/**
 * The API is asymmetric and it matters:
 *   - requests  take camelCase  (startsAt, endsAt, allDay, reminderAt)
 *   - responses come back snake_case (starts_at, ends_at, all_day, reminder_at)
 * Every shape conversion in the calendar goes through this file so the rest of
 * the UI only ever sees real Date objects.
 */

/**
 * Symfony validates every datetime with Assert\DateTime(format: ATOM), which
 * rejects trailing data. Date#toISOString() emits milliseconds ("....000Z") and
 * is therefore refused by the backend — always serialise through here.
 */
export function to_atom(date)
{
  if (!date)
  {
    return null
  }
  return format(date, "yyyy-MM-dd'T'HH:mm:ssxxx")
}

/** Raw API event (snake_case, ISO strings) -> UI event (Date objects). */
export function parse_event(raw)
{
  return {
    id: raw.id,
    title: raw.title || '',
    description: raw.description || '',
    location: raw.location || '',
    starts_at: raw.starts_at ? parseISO(raw.starts_at) : null,
    ends_at: raw.ends_at ? parseISO(raw.ends_at) : null,
    all_day: Boolean(raw.all_day),
    reminder_at: raw.reminder_at ? parseISO(raw.reminder_at) : null,
  }
}

/**
 * UI event -> request body (camelCase).
 * Always emits the *complete* object: PATCH compares `$startsAt >= $endsAt`
 * before null-checking either, and in PHP `null >= null` is true — so a partial
 * PATCH is rejected with "Starts at must be before ends at". Title carries
 * NotBlank on the update DTO too, so it can never be omitted.
 */
export function to_request_body(draft)
{
  return {
    title: draft.title.trim(),
    description: draft.description.trim(),
    location: draft.location.trim(),
    startsAt: to_atom(draft.starts_at),
    endsAt: to_atom(draft.ends_at),
    allDay: draft.all_day,
    reminderAt: draft.reminder_at ? to_atom(draft.reminder_at) : null,
  }
}

/** Pulls a human message out of either error envelope the API can return. */
export async function extract_error(response, fallback)
{
  try
  {
    const data = await response.json()

    if (data?.errors)
    {
      const first = Object.values(data.errors)[0]
      if (first)
      {
        return first
      }
    }

    if (data?.error)
    {
      return data.error
    }
  }
  catch
  {
    // No JSON body (e.g. 204 / gateway error) — fall through.
  }

  return fallback
}

/* ── colour ─────────────────────────────────────────────────────────────── */

// Categorical set only — these are decorative, never semantic.
const palette = ['var(--accent)', 'var(--gold)', 'var(--clay)', 'var(--sage)', 'var(--violet)']

/** Stable per-event hue, so an event keeps its colour across refetches. */
export function event_color(id)
{
  return palette[Math.abs(Number(id) || 0) % palette.length]
}

/** Translucent tint of a token colour — the design's chip/tile fill. */
export function tint(color, percent)
{
  return `color-mix(in srgb, ${color} ${percent}%, transparent)`
}

/* ── visible windows ────────────────────────────────────────────────────── */

/**
 * The 42-day window a month grid always shows — leading and trailing days from
 * the adjacent months included. Lives here rather than beside the component so
 * the grid files only export components (react-refresh).
 */
export function month_window(cursor)
{
  return eachDayOfInterval({
    start: startOfWeek(startOfMonth(cursor), { weekStartsOn: WEEK_STARTS_ON }),
    end: endOfWeek(endOfMonth(cursor), { weekStartsOn: WEEK_STARTS_ON }),
  })
}

export function week_window(cursor)
{
  const start = startOfWeek(cursor, { weekStartsOn: WEEK_STARTS_ON })
  return Array.from({ length: 7 }, (_, i) => addDays(start, i))
}

/* ── day maths ──────────────────────────────────────────────────────────── */

/** True when an event touches `day` at all — multi-day events span every day. */
export function occurs_on(event, day)
{
  if (!event.starts_at || !event.ends_at)
  {
    return false
  }

  const day_start = startOfDay(day)
  const day_end = endOfDay(day)

  return !isAfter(event.starts_at, day_end) && !isBefore(event.ends_at, day_start)
}

/** All-day first, then chronological, then by title so ordering never jitters. */
export function compare_events(a, b)
{
  if (a.all_day !== b.all_day)
  {
    return a.all_day ? -1 : 1
  }

  const delta = a.starts_at - b.starts_at
  if (delta !== 0)
  {
    return delta
  }

  return a.title.localeCompare(b.title)
}

export function events_for_day(events, day)
{
  return events.filter(event => occurs_on(event, day)).sort(compare_events)
}

/** Groups events by the days they touch — used by the agenda view. */
export function group_by_day(events, days)
{
  return days
    .map(day => ({ day, events: events_for_day(events, day) }))
    .filter(group => group.events.length > 0)
}

/* ── formatting ─────────────────────────────────────────────────────────── */

/** "9:00 am" — calm lowercase meridiem, matching the design's quiet register. */
export function format_time(date)
{
  return format(date, 'h:mm a').toLowerCase()
}

/** The one-line time summary shown on chips, in the detail dialog and agenda. */
export function format_event_range(event)
{
  if (event.all_day)
  {
    return 'All day'
  }

  if (!event.starts_at || !event.ends_at)
  {
    return ''
  }

  if (isSameDay(event.starts_at, event.ends_at))
  {
    return `${format_time(event.starts_at)} – ${format_time(event.ends_at)}`
  }

  return `${format(event.starts_at, 'd MMM')}, ${format_time(event.starts_at)} – ${format(event.ends_at, 'd MMM')}, ${format_time(event.ends_at)}`
}

/* ── reminder presets ───────────────────────────────────────────────────── */

export const reminder_presets = [
  { value: 'none', label: 'No reminder', minutes: null },
  { value: 'at_start', label: 'At start time', minutes: 0 },
  { value: '10', label: '10 minutes before', minutes: 10 },
  { value: '30', label: '30 minutes before', minutes: 30 },
  { value: '60', label: '1 hour before', minutes: 60 },
  { value: '1440', label: '1 day before', minutes: 1440 },
]

/** Maps a stored reminder back onto a preset, or 'custom' when it fits none. */
export function reminder_to_preset(reminder_at, starts_at)
{
  if (!reminder_at || !starts_at)
  {
    return 'none'
  }

  const minutes = differenceInMinutes(starts_at, reminder_at)
  const match = reminder_presets.find(preset => preset.minutes === minutes)

  return match ? match.value : 'custom'
}

/** Preset -> absolute reminder instant, relative to the event's start. */
export function preset_to_reminder(preset, starts_at, existing)
{
  if (preset === 'custom')
  {
    return existing
  }

  const match = reminder_presets.find(item => item.value === preset)

  if (!match || match.minutes === null || !starts_at)
  {
    return null
  }

  return new Date(starts_at.getTime() - match.minutes * 60 * 1000)
}
