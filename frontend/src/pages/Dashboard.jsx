import { useState, useEffect, useMemo } from 'react'
import { motion } from 'framer-motion'
import AppLayout from '@/components/layout/AppLayout'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { bills_due } from '@/lib/home_mock_data'
import NetWorthPanel from '@/components/home/NetWorthPanel'
import PortfolioPanel from '@/components/home/PortfolioPanel'
import VoraPanel from '@/components/home/VoraPanel'
import MeetingsPanel from '@/components/home/MeetingsPanel'
import InboxPanel from '@/components/home/InboxPanel'
import TasksPanel from '@/components/home/TasksPanel'
import CashAccountsPanel from '@/components/home/CashAccountsPanel'
import SpendingPanel from '@/components/home/SpendingPanel'
import BillsPanel from '@/components/home/BillsPanel'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 8 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.3,
      ease: [0.22, 1, 0.36, 1],
      staggerChildren: should_reduce ? 0 : 0.06,
    },
  },
})

const get_section_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.25,
      ease: [0.22, 1, 0.36, 1],
      staggerChildren: should_reduce ? 0 : 0.05,
    },
  },
})

const pad2 = (value) => String(value).padStart(2, '0')

/**
 * The event API parses `from`/`to` with DateTimeInterface::ATOM, which does NOT
 * accept the `Z` suffix or milliseconds that Date#toISOString emits. Build the
 * offset form by hand.
 */
const to_atom = (date) =>
{
  const offset_minutes = -date.getTimezoneOffset()
  const sign = offset_minutes >= 0 ? '+' : '-'
  const absolute = Math.abs(offset_minutes)

  const calendar = `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
  const clock = `${pad2(date.getHours())}:${pad2(date.getMinutes())}:${pad2(date.getSeconds())}`
  const zone = `${sign}${pad2(Math.floor(absolute / 60))}:${pad2(absolute % 60)}`

  return `${calendar}T${clock}${zone}`
}

const get_greeting = () =>
{
  const hour = new Date().getHours()

  if (hour < 12)
  {
    return 'Good morning'
  }

  if (hour < 18)
  {
    return 'Good afternoon'
  }

  return 'Good evening'
}

const format_task_due = (due_date) =>
{
  if (!due_date)
  {
    return null
  }

  const date = new Date(due_date.split(' ')[0])

  if (Number.isNaN(date.getTime()))
  {
    return null
  }

  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

/** Flatten the dashboard's three task buckets into one ordered row list. */
const flatten_tasks = (tasks) =>
{
  const buckets = [
    ['overdue', tasks?.overdue],
    ['today', tasks?.today],
    ['upcoming', tasks?.upcoming],
  ]

  const rows = []

  buckets.forEach(([bucket, list]) =>
  {
    if (!Array.isArray(list))
    {
      return
    }

    list.forEach((task) =>
    {
      rows.push({
        ...task,
        bucket,
        due_label: bucket === 'today' ? 'Today' : format_task_due(task.due_date),
      })
    })
  })

  return rows
}

/** Normalise an event: the API serialises snake_case (starts_at / all_day). */
const normalise_event = (event, now) =>
{
  const starts_at = event.starts_at ?? event.startsAt
  const all_day = event.all_day ?? event.allDay ?? false
  const start = starts_at ? new Date(starts_at) : null
  const is_valid = start && !Number.isNaN(start.getTime())

  return {
    id: event.id,
    title: event.title,
    location: event.location,
    start: is_valid ? start : null,
    all_day,
    is_past: is_valid ? start.getTime() < now.getTime() : false,
    time_label: all_day || !is_valid
      ? 'All day'
      : `${pad2(start.getHours())}:${pad2(start.getMinutes())}`,
  }
}

export default function Dashboard()
{
  const { user, api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const [dashboard_data, set_dashboard_data] = useState(null)
  const [events, set_events] = useState([])
  const [tasks_loading, set_tasks_loading] = useState(true)
  const [events_loading, set_events_loading] = useState(true)
  const [tasks_error, set_tasks_error] = useState(false)
  const [events_error, set_events_error] = useState(false)

  const page_variants = get_page_variants(should_reduce_motion)
  const section_variants = get_section_variants(should_reduce_motion)

  const fetch_dashboard_data = async () =>
  {
    set_tasks_loading(true)
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/dashboard/`)

      if (!response.ok)
      {
        throw new Error('Request failed')
      }

      set_dashboard_data(await response.json())
      set_tasks_error(false)
    }
    catch (error)
    {
      console.error('Failed to fetch dashboard data:', error)
      set_tasks_error(true)
    }
    finally
    {
      set_tasks_loading(false)
    }
  }

  const fetch_todays_events = async () =>
  {
    set_events_loading(true)
    try
    {
      const start_of_day = new Date()
      start_of_day.setHours(0, 0, 0, 0)

      const end_of_day = new Date()
      end_of_day.setHours(23, 59, 59, 0)

      const query = new URLSearchParams({
        from: to_atom(start_of_day),
        to: to_atom(end_of_day),
      })

      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/event?${query.toString()}`)

      if (!response.ok)
      {
        throw new Error('Request failed')
      }

      const data = await response.json()
      set_events(Array.isArray(data) ? data : [])
      set_events_error(false)
    }
    catch (error)
    {
      console.error('Failed to fetch events:', error)
      set_events_error(true)
      set_events([])
    }
    finally
    {
      set_events_loading(false)
    }
  }

  useEffect(() =>
  {
    fetch_dashboard_data()
    fetch_todays_events()
  }, [])

  const toggle_task_status = async (task) =>
  {
    const new_status = task.status === 'done' ? 'todo' : 'done'

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task/${task.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...task,
          status: new_status,
        }),
      })

      if (response.ok)
      {
        fetch_dashboard_data()
      }
    }
    catch (error)
    {
      console.error('Failed to update task:', error)
    }
  }

  const task_rows = useMemo(() => flatten_tasks(dashboard_data?.tasks), [dashboard_data])
  const done_count = task_rows.filter((task) => task.status === 'done').length
  const open_count = task_rows.length - done_count

  const meetings = useMemo(() =>
  {
    const now = new Date()

    return events
      .map((event) => normalise_event(event, now))
      .sort((a, b) => (a.start?.getTime() ?? 0) - (b.start?.getTime() ?? 0))
  }, [events])

  const next_meeting = meetings.find((meeting) => !meeting.is_past) ?? null

  const first_name = useMemo(() =>
  {
    const given = user?.first_name || user?.firstName

    if (given)
    {
      return given
    }

    if (!user?.email)
    {
      return 'there'
    }

    const handle = user.email.split('@')[0].replace(/[0-9]/g, '').split(/[._-]/)[0]

    if (!handle)
    {
      return 'there'
    }

    return handle.charAt(0).toUpperCase() + handle.slice(1)
  }, [user])

  const today_label = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })

  const status_line = useMemo(() =>
  {
    const parts = []

    if (open_count > 0)
    {
      parts.push(`${open_count} ${open_count === 1 ? 'task needs' : 'tasks need'} you`)
    }

    if (next_meeting)
    {
      parts.push(`next meeting ${next_meeting.time_label}`)
    }

    const next_bill = bills_due[0]

    if (next_bill)
    {
      parts.push(`${next_bill.name.toLowerCase()} ${next_bill.due.toLowerCase()}`)
    }

    if (parts.length === 0)
    {
      return 'Nothing needs you right now.'
    }

    return parts.join(' · ')
  }, [open_count, next_meeting])

  return (
    <AppLayout>
      <motion.div initial="hidden" animate="visible" variants={page_variants}>
        {/* Greeting */}
        <motion.header
          className="mb-[18px] flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
          variants={section_variants}
        >
          <div className="min-w-0">
            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-faint">
              {today_label}
            </p>
            <h1 className="mt-1.5 text-[29px] font-bold leading-tight tracking-[-0.025em]">
              {get_greeting()}, {first_name}
            </h1>
          </div>

          <p className="text-[13.5px] text-dim sm:text-right">{status_line}</p>
        </motion.header>

        {/* Row 1 — Net worth · Portfolio · Vora */}
        <motion.section
          className="mb-[18px] grid grid-cols-1 gap-[18px] md:grid-cols-2 lg:grid-cols-12"
          variants={section_variants}
        >
          <motion.div className="min-w-0 lg:col-span-5" variants={section_variants}>
            <NetWorthPanel/>
          </motion.div>
          <motion.div className="min-w-0 lg:col-span-4" variants={section_variants}>
            <PortfolioPanel/>
          </motion.div>
          <motion.div className="min-w-0 md:col-span-2 lg:col-span-3" variants={section_variants}>
            <VoraPanel/>
          </motion.div>
        </motion.section>

        {/* Row 2 — Meetings · Inbox · Tasks */}
        <motion.section
          className="mb-[18px] grid grid-cols-1 gap-[18px] md:grid-cols-2 lg:grid-cols-12"
          variants={section_variants}
        >
          <motion.div className="min-w-0 lg:col-span-5" variants={section_variants}>
            <MeetingsPanel
              events={meetings}
              loading={events_loading}
              error={events_error}
              next_id={next_meeting?.id ?? null}
            />
          </motion.div>
          <motion.div className="min-w-0 lg:col-span-4" variants={section_variants}>
            <InboxPanel/>
          </motion.div>
          <motion.div className="min-w-0 md:col-span-2 lg:col-span-3" variants={section_variants}>
            <TasksPanel
              tasks={task_rows}
              done={done_count}
              total={task_rows.length}
              loading={tasks_loading}
              error={tasks_error}
              on_toggle={toggle_task_status}
            />
          </motion.div>
        </motion.section>

        {/* Row 3 — Cash · Spending · Bills */}
        <motion.section
          className="grid grid-cols-1 gap-[18px] md:grid-cols-2 lg:grid-cols-12"
          variants={section_variants}
        >
          <motion.div className="min-w-0 lg:col-span-5" variants={section_variants}>
            <CashAccountsPanel/>
          </motion.div>
          <motion.div className="min-w-0 lg:col-span-4" variants={section_variants}>
            <SpendingPanel/>
          </motion.div>
          <motion.div className="min-w-0 md:col-span-2 lg:col-span-3" variants={section_variants}>
            <BillsPanel/>
          </motion.div>
        </motion.section>
      </motion.div>
    </AppLayout>
  )
}
