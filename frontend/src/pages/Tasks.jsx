import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  PlusIcon,
  Loader2Icon,
  CheckCircle2Icon,
  ClockIcon,
  AlertCircleIcon,
  CircleDashedIcon,
  CircleDotIcon,
  LayersIcon,
  ListChecksIcon,
  RotateCwIcon,
  WifiOffIcon,
  SparklesIcon,
} from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { cn } from '@/lib/utils'
import TaskItem from '@/components/TaskItem'
import TaskForm from '@/components/TaskForm'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.5,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

const get_container_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: should_reduce ? 0 : 0.05,
      delayChildren: should_reduce ? 0 : 0.1,
    },
  },
})

const get_item_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.4,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: 0,
    y: should_reduce ? 0 : -8,
    transition: {
      duration: should_reduce ? 0 : 0.2,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

const category_tabs = [
  { value: 'all', label: 'All', icon: LayersIcon, count_key: 'all' },
  { value: 'today', label: 'Today', icon: ClockIcon, count_key: 'today' },
  { value: 'overdue', label: 'Overdue', icon: AlertCircleIcon, count_key: 'overdue' },
]

const status_tabs = [
  { value: 'all', label: 'All', icon: ListChecksIcon, count_key: 'status_all' },
  { value: 'todo', label: 'To Do', icon: CircleDashedIcon, count_key: 'todo' },
  { value: 'in_progress', label: 'In Progress', icon: CircleDotIcon, count_key: 'in_progress' },
  { value: 'done', label: 'Done', icon: CheckCircle2Icon, count_key: 'done' },
]

const empty_state_icons = {
  all: LayersIcon,
  today: ClockIcon,
  overdue: CheckCircle2Icon,
  todo: CircleDashedIcon,
  in_progress: CircleDotIcon,
  done: CheckCircle2Icon,
}

function FilterPill({ is_active, icon, label, count, size, on_click })
{
  const PillIcon = icon

  return (
    <button
      type="button"
      role="tab"
      aria-selected={is_active}
      onClick={on_click}
      className={cn(
        'inline-flex cursor-pointer items-center gap-2 rounded-[20px] transition-colors duration-150',
        size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-3.5 py-2 text-sm',
        is_active
          ? 'bg-[color-mix(in_srgb,var(--accent)_14%,transparent)] font-bold text-ink'
          : 'font-medium text-dim hover:bg-panel-sunk hover:text-ink'
      )}
    >
      <PillIcon className={size === 'sm' ? 'size-3.5' : 'size-4'}/>
      {label}
      {count > 0 && (
        <span
          className={cn(
            'num inline-flex min-w-[1.25rem] justify-center rounded-[20px] px-1.5 py-px text-[11px] font-semibold',
            is_active
              ? 'bg-accent text-on-accent'
              : 'bg-panel-sunk text-faint'
          )}
        >
          {count}
        </span>
      )}
    </button>
  )
}

export default function Tasks()
{
  const { api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const page_variants = get_page_variants(should_reduce_motion)
  const container_variants = get_container_variants(should_reduce_motion)
  const item_variants = get_item_variants(should_reduce_motion)

  const [all_tasks, set_all_tasks] = useState([])
  const [loading, set_loading] = useState(true)
  const [error, set_error] = useState(null)
  const [category, set_category] = useState('all') // 'all', 'today', 'overdue'
  const [status_filter, set_status_filter] = useState('all') // 'all', 'todo', 'in_progress', 'done'
  const [show_form, set_show_form] = useState(false)
  const [editing_task, set_editing_task] = useState(null)

  const fetch_tasks = async () =>
  {
    set_loading(true)
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task/`)
      if (response.ok)
      {
        const data = await response.json()
        set_all_tasks(data)
        set_error(null)
      }
      else
      {
        console.error('Failed to fetch tasks, status:', response.status)
        set_error('We could not load your tasks just now.')
      }
    }
    catch (error)
    {
      console.error('Failed to fetch tasks:', error)
      set_error('We could not reach the server. Check your connection and try again.')
    }
    finally
    {
      set_loading(false)
    }
  }

  useEffect(() =>
  {
    fetch_tasks()
  }, [])

  const handle_create_task = async (task_data) =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(task_data),
      })

      if (response.ok)
      {
        set_show_form(false)
        fetch_tasks()
      }
    }
    catch (error)
    {
      console.error('Failed to create task:', error)
    }
  }

  const handle_update_task = async (id, task_data) =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(task_data),
      })

      if (response.ok)
      {
        set_editing_task(null)
        fetch_tasks()
      }
    }
    catch (error)
    {
      console.error('Failed to update task:', error)
    }
  }

  const handle_delete_task = async (id) =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task/${id}`, {
        method: 'DELETE',
      })

      if (response.ok)
      {
        fetch_tasks()
      }
    }
    catch (error)
    {
      console.error('Failed to delete task:', error)
    }
  }

  const handle_toggle_status = async (task) =>
  {
    const new_status = task.status === 'done' ? 'todo' : 'done'
    await handle_update_task(task.id, { ...task, status: new_status })
  }

  const get_filtered_tasks = () =>
  {
    const today = new Date().toISOString().split('T')[0]
    let filtered = all_tasks

    // First filter by category
    if (category === 'today')
    {
      filtered = filtered.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] === today
      })
    }
    else if (category === 'overdue')
    {
      filtered = filtered.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] < today && t.status !== 'done'
      })
    }

    // Then filter by status
    if (status_filter === 'todo')
    {
      filtered = filtered.filter(t => t.status === 'todo')
    }
    else if (status_filter === 'in_progress')
    {
      filtered = filtered.filter(t => t.status === 'in_progress')
    }
    else if (status_filter === 'done')
    {
      filtered = filtered.filter(t => t.status === 'done')
    }

    return filtered
  }

  const get_task_counts = () =>
  {
    const today = new Date().toISOString().split('T')[0]

    // Get category-filtered tasks first
    let category_tasks = all_tasks
    if (category === 'today')
    {
      category_tasks = all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] === today
      })
    }
    else if (category === 'overdue')
    {
      category_tasks = all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] < today && t.status !== 'done'
      })
    }

    return {
      // Category counts
      all: all_tasks.length,
      today: all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] === today
      }).length,
      overdue: all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] < today && t.status !== 'done'
      }).length,
      // Status counts within current category
      status_all: category_tasks.length,
      todo: category_tasks.filter(t => t.status === 'todo').length,
      in_progress: category_tasks.filter(t => t.status === 'in_progress').length,
      done: category_tasks.filter(t => t.status === 'done').length,
    }
  }

  const get_list_title = () =>
  {
    const category_label = {
      all: 'All',
      today: "Today's",
      overdue: 'Overdue',
    }[category]

    const status_label = {
      all: 'tasks',
      todo: 'to do tasks',
      in_progress: 'in progress tasks',
      done: 'completed tasks',
    }[status_filter]

    return `${category_label} ${status_label}`
  }

  const get_empty_copy = () =>
  {
    if (category === 'overdue')
    {
      return status_filter === 'all'
        ? 'Nothing has slipped past its due date. Good.'
        : `No overdue ${status_filter.replace('_', ' ')} tasks.`
    }

    if (category === 'today')
    {
      return status_filter === 'all'
        ? 'Nothing is due today. The day is yours.'
        : `No ${status_filter.replace('_', ' ')} tasks due today.`
    }

    return `No ${status_filter.replace('_', ' ')} tasks yet.`
  }

  const reset_filters = () =>
  {
    set_category('all')
    set_status_filter('all')
  }

  const filtered_tasks = get_filtered_tasks()
  const counts = get_task_counts()
  const is_first_run = !loading && !error && all_tasks.length === 0
  const EmptyIcon = empty_state_icons[status_filter === 'all' ? category : status_filter] || LayersIcon

  return (
    <AppLayout>
      <motion.div
        className="space-y-8"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        {/* Page header */}
        <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-1">
            <h1 className="text-[29px] font-bold leading-tight tracking-[-0.025em] text-ink">
              Tasks
            </h1>
            <p className="text-sm text-dim">
              Everything you have committed to, in one quiet place.
            </p>
          </div>

          <Button
            onClick={() => set_show_form(true)}
            className="shrink-0 cursor-pointer gap-2 rounded-[11px] bg-accent text-on-accent hover:bg-[color-mix(in_srgb,var(--ink)_12%,var(--accent))]"
          >
            <PlusIcon className="size-4"/>
            New Task
          </Button>
        </header>

        {/* Filters */}
        <div className="space-y-3">
          <div className="flex flex-wrap gap-1" role="tablist" aria-label="Filter tasks by date">
            {category_tabs.map((tab) => (
              <FilterPill
                key={tab.value}
                is_active={category === tab.value}
                icon={tab.icon}
                label={tab.label}
                count={counts[tab.count_key]}
                on_click={() => set_category(tab.value)}
              />
            ))}
          </div>

          <div
            className="flex flex-wrap gap-1 border-t border-line2 pt-3"
            role="tablist"
            aria-label="Filter tasks by status"
          >
            {status_tabs.map((tab) => (
              <FilterPill
                key={tab.value}
                is_active={status_filter === tab.value}
                icon={tab.icon}
                label={tab.label}
                count={counts[tab.count_key]}
                size="sm"
                on_click={() => set_status_filter(tab.value)}
              />
            ))}
          </div>
        </div>

        {/* List */}
        <section className="space-y-4">
          <div className="flex items-baseline justify-between gap-4">
            <h2 className="eyebrow">{get_list_title()}</h2>
            {!loading && !error && filtered_tasks.length > 0 && (
              <span className="num text-xs text-faint">
                {filtered_tasks.length} shown
              </span>
            )}
          </div>

          {loading ? (
            <div className="panel flex flex-col items-center justify-center px-6 py-16">
              <Loader2Icon className="mb-3 size-7 animate-spin text-faint"/>
              <p className="text-sm text-dim">Gathering your tasks...</p>
            </div>
          ) : error ? (
            <div className="panel flex flex-col items-center justify-center px-6 py-16 text-center">
              <div
                className="mb-4 flex size-12 items-center justify-center rounded-[20px]"
                style={{ backgroundColor: 'color-mix(in srgb, var(--down) 13%, transparent)' }}
              >
                <WifiOffIcon className="size-6 text-down"/>
              </div>
              <p className="mb-1 text-sm font-semibold text-ink">Something went quiet</p>
              <p className="mb-5 max-w-sm text-xs text-dim">{error}</p>
              <Button
                variant="outline"
                onClick={fetch_tasks}
                className="cursor-pointer gap-2 rounded-[11px]"
              >
                <RotateCwIcon className="size-4"/>
                Try again
              </Button>
            </div>
          ) : is_first_run ? (
            <div className="panel flex flex-col items-center justify-center px-6 py-16 text-center">
              <div
                className="mb-4 flex size-12 items-center justify-center rounded-[20px]"
                style={{ backgroundColor: 'color-mix(in srgb, var(--accent) 13%, transparent)' }}
              >
                <SparklesIcon className="size-6 text-accent"/>
              </div>
              <p className="mb-1 text-sm font-semibold text-ink">Your archive is empty</p>
              <p className="mb-5 max-w-sm text-xs text-dim">
                Start with one thing you want to get done. You can add the details later.
              </p>
              <Button
                onClick={() => set_show_form(true)}
                className="cursor-pointer gap-2 rounded-[11px] bg-accent text-on-accent hover:bg-[color-mix(in_srgb,var(--ink)_12%,var(--accent))]"
              >
                <PlusIcon className="size-4"/>
                Create your first task
              </Button>
            </div>
          ) : filtered_tasks.length === 0 ? (
            <div className="panel flex flex-col items-center justify-center px-6 py-16 text-center">
              <div className="mb-4 flex size-12 items-center justify-center rounded-[20px] bg-panel-sunk">
                <EmptyIcon className="size-6 text-faint"/>
              </div>
              <p className="mb-1 text-sm font-semibold text-ink">Nothing here</p>
              <p className="mb-5 max-w-sm text-xs text-dim">{get_empty_copy()}</p>
              <Button
                variant="outline"
                onClick={reset_filters}
                className="cursor-pointer gap-2 rounded-[11px]"
              >
                <LayersIcon className="size-4"/>
                Show all tasks
              </Button>
            </div>
          ) : (
            <motion.div
              className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
              initial="hidden"
              animate="visible"
              variants={container_variants}
            >
              <AnimatePresence mode="popLayout">
                {filtered_tasks.map((task) => (
                  <motion.div
                    key={task.id}
                    variants={item_variants}
                    initial="hidden"
                    animate="visible"
                    exit="exit"
                    layout={!should_reduce_motion}
                  >
                    <TaskItem
                      task={task}
                      on_toggle_status={handle_toggle_status}
                      on_edit={() => set_editing_task(task)}
                      on_delete={handle_delete_task}
                    />
                  </motion.div>
                ))}
              </AnimatePresence>
            </motion.div>
          )}
        </section>

        {show_form && (
          <TaskForm
            on_submit={handle_create_task}
            on_cancel={() => set_show_form(false)}
          />
        )}

        {editing_task && (
          <TaskForm
            task={editing_task}
            on_submit={(data) => handle_update_task(editing_task.id, data)}
            on_cancel={() => set_editing_task(null)}
          />
        )}
      </motion.div>
    </AppLayout>
  )
}
