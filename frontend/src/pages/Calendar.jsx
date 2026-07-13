import { useState, useEffect, useMemo, useCallback } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  addMonths,
  subMonths,
  addWeeks,
  subWeeks,
  startOfDay,
  endOfDay,
  format,
  isSameMonth,
} from 'date-fns'
import { AlertCircle } from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import CalendarToolbar from '@/components/calendar/CalendarToolbar'
import MonthGrid from '@/components/calendar/MonthGrid'
import WeekGrid from '@/components/calendar/WeekGrid'
import AgendaList from '@/components/calendar/AgendaList'
import EmptyMonth from '@/components/calendar/EmptyMonth'
import EventFormDialog from '@/components/calendar/EventFormDialog'
import EventDetailDialog from '@/components/calendar/EventDetailDialog'
import DayPeekDialog from '@/components/calendar/DayPeekDialog'
import {
  parse_event,
  to_atom,
  to_request_body,
  extract_error,
  month_window,
  week_window,
} from '@/components/calendar/event_utils'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 8 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.3,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

const get_surface_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 6 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.22,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: should_reduce ? 1 : 0,
    transition: { duration: should_reduce ? 0 : 0.12 },
  },
})

export default function Calendar()
{
  const { api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const [view, set_view] = useState('month')
  const [cursor, set_cursor] = useState(() => new Date())

  const [events, set_events] = useState([])
  const [loading, set_loading] = useState(true)
  const [error, set_error] = useState('')

  const [form_open, set_form_open] = useState(false)
  const [form_event, set_form_event] = useState(null)
  const [form_date, set_form_date] = useState(null)
  const [form_error, set_form_error] = useState('')
  const [saving, set_saving] = useState(false)

  const [detail_open, set_detail_open] = useState(false)
  const [detail_event, set_detail_event] = useState(null)
  const [detail_error, set_detail_error] = useState('')
  const [deleting, set_deleting] = useState(false)

  const [peek_open, set_peek_open] = useState(false)
  const [peek_day, set_peek_day] = useState(null)

  const page_variants = get_page_variants(should_reduce_motion)
  const surface_variants = get_surface_variants(should_reduce_motion)

  // The days currently on screen — also the range we ask the API for.
  const visible_days = useMemo(() =>
  {
    return view === 'week' ? week_window(cursor) : month_window(cursor)
  }, [view, cursor])

  const range_from = to_atom(startOfDay(visible_days[0]))
  const range_to = to_atom(endOfDay(visible_days[visible_days.length - 1]))

  const fetch_events = useCallback(async () =>
  {
    set_loading(true)
    set_error('')

    try
    {
      const query = new URLSearchParams({ from: range_from, to: range_to })
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/event?${query.toString()}`
      )

      if (!response.ok)
      {
        throw new Error(await extract_error(response, 'Failed to load events'))
      }

      // This endpoint returns a bare array, not an { events: [...] } envelope.
      const data = await response.json()
      set_events((Array.isArray(data) ? data : []).map(parse_event))
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_loading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [range_from, range_to])

  useEffect(() =>
  {
    fetch_events()
  }, [fetch_events])

  /* ── navigation ───────────────────────────────────────────────────────── */

  const handle_prev = () =>
  {
    set_cursor(current => (view === 'week' ? subWeeks(current, 1) : subMonths(current, 1)))
  }

  const handle_next = () =>
  {
    set_cursor(current => (view === 'week' ? addWeeks(current, 1) : addMonths(current, 1)))
  }

  const handle_today = () =>
  {
    set_cursor(new Date())
  }

  /* ── dialogs ──────────────────────────────────────────────────────────── */

  const open_create = (day) =>
  {
    set_form_event(null)
    set_form_date(day instanceof Date ? day : null)
    set_form_error('')
    set_peek_open(false)
    set_form_open(true)
  }

  const open_event = (event) =>
  {
    set_detail_event(event)
    set_detail_error('')
    set_peek_open(false)
    set_detail_open(true)
  }

  const open_day = (day) =>
  {
    set_peek_day(day)
    set_peek_open(true)
  }

  const open_edit = (event) =>
  {
    set_detail_open(false)
    set_form_event(event)
    set_form_date(null)
    set_form_error('')
    set_form_open(true)
  }

  /* ── mutations ────────────────────────────────────────────────────────── */

  const handle_submit = async (draft) =>
  {
    set_saving(true)
    set_form_error('')

    const is_edit = Boolean(form_event)

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/event${is_edit ? `/${form_event.id}` : ''}`,
        {
          method: is_edit ? 'PATCH' : 'POST',
          // to_request_body always sends the complete object: a partial PATCH
          // trips the backend's `$startsAt >= $endsAt` check (null >= null is
          // true in PHP) and its NotBlank title rule.
          body: JSON.stringify(to_request_body(draft)),
        }
      )

      if (!response.ok)
      {
        throw new Error(await extract_error(
          response,
          is_edit ? 'Failed to update event' : 'Failed to create event'
        ))
      }

      set_form_open(false)
      set_form_event(null)
      set_form_date(null)
      await fetch_events()
    }
    catch (err)
    {
      set_form_error(err.message)
    }
    finally
    {
      set_saving(false)
    }
  }

  const handle_delete = async (event) =>
  {
    set_deleting(true)
    set_detail_error('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/event/${event.id}`,
        { method: 'DELETE' }
      )

      // 204 No Content — there is no body to parse on success.
      if (!response.ok)
      {
        throw new Error(await extract_error(response, 'Failed to delete event'))
      }

      set_detail_open(false)
      set_detail_event(null)
      await fetch_events()
    }
    catch (err)
    {
      set_detail_error(err.message)
    }
    finally
    {
      set_deleting(false)
    }
  }

  /* ── labels ───────────────────────────────────────────────────────────── */

  const heading = useMemo(() =>
  {
    if (view !== 'week')
    {
      return format(cursor, 'MMMM yyyy')
    }

    const days = week_window(cursor)
    const first = days[0]
    const last = days[6]

    return isSameMonth(first, last)
      ? `${format(first, 'd')} – ${format(last, 'd MMMM yyyy')}`
      : `${format(first, 'd MMM')} – ${format(last, 'd MMM yyyy')}`
  }, [view, cursor])

  const sub_heading = loading
    ? 'Loading…'
    : `${events.length} ${events.length === 1 ? 'event' : 'events'} · today is ${format(new Date(), 'd MMM yyyy')}`

  const period_label = view === 'week' ? 'This week' : format(cursor, 'MMMM')

  const is_empty = !loading && !error && events.length === 0

  return (
    <AppLayout>
      <motion.div
        className="flex flex-col gap-6"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        <CalendarToolbar
          heading={heading}
          sub_heading={sub_heading}
          view={view}
          on_view_change={set_view}
          on_prev={handle_prev}
          on_next={handle_next}
          on_today={handle_today}
          on_create={open_create}
        />

        {error && (
          <div
            role="alert"
            className="flex items-start gap-2.5 rounded-[11px] px-4 py-3 text-[13px] font-medium text-down"
            style={{ background: 'color-mix(in srgb, var(--down) 11%, transparent)' }}
          >
            <AlertCircle className="mt-px h-4 w-4 flex-none"/>
            <div className="flex flex-col gap-1">
              <span>{error}</span>
              <button
                type="button"
                onClick={fetch_events}
                className="w-fit text-[12.5px] font-bold underline underline-offset-2"
              >
                Try again
              </button>
            </div>
          </div>
        )}

        {loading && (
          <div className="panel flex min-h-[420px] items-center justify-center">
            <span
              className="text-[13px] text-dim"
              style={{ animation: 'pulse 1.5s ease-in-out infinite' }}
            >
              Loading your calendar…
            </span>
          </div>
        )}

        {!loading && !error && (
          <AnimatePresence mode="wait">
            <motion.div
              key={`${view}-${format(cursor, 'yyyy-MM-dd')}`}
              variants={surface_variants}
              initial="hidden"
              animate="visible"
              exit="exit"
            >
              {/* An empty period still has to look composed — never a bare grid. */}
              {is_empty && view !== 'agenda' && (
                <EmptyMonth period_label={period_label} on_create={open_create}/>
              )}

              {!is_empty && view === 'month' && (
                <MonthGrid
                  cursor={cursor}
                  events={events}
                  on_open_event={open_event}
                  on_open_day={open_day}
                  on_create_on_day={open_create}
                />
              )}

              {!is_empty && view === 'week' && (
                <WeekGrid
                  cursor={cursor}
                  events={events}
                  on_open_event={open_event}
                  on_create_on_day={open_create}
                />
              )}

              {view === 'agenda' && (
                <AgendaList
                  days={visible_days}
                  events={events}
                  on_open_event={open_event}
                  on_create={open_create}
                  period_label={period_label}
                />
              )}
            </motion.div>
          </AnimatePresence>
        )}
      </motion.div>

      <EventFormDialog
        open={form_open}
        on_open_change={set_form_open}
        event={form_event}
        initial_date={form_date}
        on_submit={handle_submit}
        saving={saving}
        error={form_error}
      />

      <EventDetailDialog
        open={detail_open}
        on_open_change={set_detail_open}
        event={detail_event}
        on_edit={open_edit}
        on_delete={handle_delete}
        deleting={deleting}
        error={detail_error}
      />

      <DayPeekDialog
        open={peek_open}
        on_open_change={set_peek_open}
        day={peek_day}
        events={events}
        on_open_event={open_event}
        on_create_on_day={open_create}
      />
    </AppLayout>
  )
}
