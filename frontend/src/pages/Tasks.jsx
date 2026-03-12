import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { PlusIcon, Loader2Icon, CheckCircle2Icon, ClockIcon, AlertCircleIcon } from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { cn } from '@/lib/utils'
import TaskItem from '@/components/TaskItem'
import TaskForm from '@/components/TaskForm'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 20 },
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
      staggerChildren: should_reduce ? 0 : 0.1,
      delayChildren: should_reduce ? 0 : 0.2,
    },
  },
})

const get_item_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 20, scale: should_reduce ? 1 : 0.95 },
  visible: {
    opacity: 1,
    y: 0,
    scale: 1,
    transition: {
      duration: should_reduce ? 0 : 0.4,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: 0,
    x: should_reduce ? 0 : -30,
    scale: should_reduce ? 1 : 0.9,
    transition: {
      duration: should_reduce ? 0 : 0.3,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

export default function Tasks()
{
  const { api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const page_variants = get_page_variants(should_reduce_motion)
  const container_variants = get_container_variants(should_reduce_motion)
  const item_variants = get_item_variants(should_reduce_motion)

  const [all_tasks, set_all_tasks] = useState([])
  const [loading, set_loading] = useState(true)
  const [filter, set_filter] = useState('all') // 'all', 'today', 'overdue'
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
        console.log('Fetched tasks:', data)
        set_all_tasks(data)
      }
      else
      {
        console.error('Failed to fetch tasks, status:', response.status)
      }
    }
    catch (error)
    {
      console.error('Failed to fetch tasks:', error)
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

    if (filter === 'today')
    {
      return all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] === today && t.status !== 'done'
      })
    }
    else if (filter === 'overdue')
    {
      return all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] < today && t.status !== 'done'
      })
    }

    return all_tasks
  }

  const get_task_counts = () =>
  {
    const today = new Date().toISOString().split('T')[0]

    return {
      all: all_tasks.length,
      today: all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] === today && t.status !== 'done'
      }).length,
      overdue: all_tasks.filter(t => {
        if (!t.due_date) return false
        return t.due_date.split(' ')[0] < today && t.status !== 'done'
      }).length,
    }
  }

  const filtered_tasks = get_filtered_tasks()
  const counts = get_task_counts()

  console.log('All tasks:', all_tasks.length, 'Filtered tasks:', filtered_tasks.length, 'Filter:', filter)

  return (
    <AppLayout title="Tasks">
      <motion.div
        className="p-6 space-y-6"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-[#e5e5e5] mb-1">Tasks</h1>
            <p className="text-sm text-[#888888]">Manage your tasks and to-dos</p>
          </div>
          <Button
            onClick={() => set_show_form(true)}
            size="default"
            className="gap-2 shadow-sm cursor-pointer"
          >
            <PlusIcon className="size-4"/>
            New Task
          </Button>
        </div>

        {/* Filter Tabs */}
        <div className="flex flex-wrap gap-3">
          <Button
            variant={filter === 'all' ? 'default' : 'outline'}
            onClick={() => set_filter('all')}
            size="default"
            className={cn(
              'gap-2 transition-all cursor-pointer',
              filter === 'all'
                ? 'bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d5d5d5] shadow-md border-[#e5e5e5]'
                : 'hover:bg-[#1a1a1a] hover:border-[#2a2a2a]'
            )}
          >
            <CheckCircle2Icon className="size-4"/>
            All
            {counts.all > 0 && (
              <Badge
                variant="secondary"
                className={cn(
                  "ml-1 px-1.5 min-w-[1.25rem] justify-center",
                  filter === 'all' ? 'bg-[#0a0a0a] text-[#e5e5e5]' : ''
                )}
              >
                {counts.all}
              </Badge>
            )}
          </Button>
          <Button
            variant={filter === 'today' ? 'default' : 'outline'}
            onClick={() => set_filter('today')}
            size="default"
            className={cn(
              'gap-2 transition-all cursor-pointer',
              filter === 'today'
                ? 'bg-blue-500 text-white hover:bg-blue-600 shadow-md border-blue-500'
                : 'hover:bg-[#1a1a1a] hover:border-[#2a2a2a]'
            )}
          >
            <ClockIcon className="size-4"/>
            Today
            {counts.today > 0 && (
              <Badge
                variant="secondary"
                className={cn(
                  "ml-1 px-1.5 min-w-[1.25rem] justify-center",
                  filter === 'today' ? 'bg-blue-700 text-white border-blue-700' : ''
                )}
              >
                {counts.today}
              </Badge>
            )}
          </Button>
          <Button
            variant={filter === 'overdue' ? 'default' : 'outline'}
            onClick={() => set_filter('overdue')}
            size="default"
            className={cn(
              'gap-2 transition-all cursor-pointer',
              filter === 'overdue'
                ? 'bg-red-500 text-white hover:bg-red-600 shadow-md border-red-500'
                : 'hover:bg-[#1a1a1a] hover:border-[#2a2a2a]'
            )}
          >
            <AlertCircleIcon className="size-4"/>
            Overdue
            {counts.overdue > 0 && (
              <Badge
                variant="destructive"
                className={cn(
                  "ml-1 px-1.5 min-w-[1.25rem] justify-center",
                  filter === 'overdue' ? 'bg-red-700 text-white border-red-700' : ''
                )}
              >
                {counts.overdue}
              </Badge>
            )}
          </Button>
        </div>

        {/* Task List */}
        <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader className="pb-4">
            <CardTitle className="text-[#e5e5e5] text-lg">
              {filter === 'all' && 'All Tasks'}
              {filter === 'today' && "Today's Tasks"}
              {filter === 'overdue' && 'Overdue Tasks'}
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-0">
            {loading ? (
              <motion.div
                className="flex flex-col items-center justify-center py-12"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.2 }}
              >
                <Loader2Icon className="size-8 animate-spin text-[#888888] mb-3"/>
                <p className="text-sm text-[#666666]">Loading tasks...</p>
              </motion.div>
            ) : filtered_tasks.length === 0 ? (
              <motion.div
                className="flex flex-col items-center justify-center py-12"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
              >
                <div className="rounded-full bg-[#1a1a1a] p-4 mb-4">
                  {filter === 'all' && <CheckCircle2Icon className="size-8 text-[#666666]"/>}
                  {filter === 'today' && <ClockIcon className="size-8 text-[#666666]"/>}
                  {filter === 'overdue' && <AlertCircleIcon className="size-8 text-[#666666]"/>}
                </div>
                <p className="text-sm font-medium text-[#888888] mb-1">
                  {filter === 'all' && 'No tasks yet'}
                  {filter === 'today' && 'No tasks due today'}
                  {filter === 'overdue' && 'No overdue tasks'}
                </p>
                <p className="text-xs text-[#666666]">
                  {filter === 'all' && 'Create your first task to get started'}
                  {filter === 'today' && 'You\'re all caught up for today!'}
                  {filter === 'overdue' && 'Great job staying on track!'}
                </p>
              </motion.div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 p-4">
                {filtered_tasks.map((task) => (
                  <TaskItem
                    key={task.id}
                    task={task}
                    on_toggle_status={handle_toggle_status}
                    on_edit={() => set_editing_task(task)}
                    on_delete={handle_delete_task}
                  />
                ))}
              </div>
            )}
          </CardContent>
        </Card>

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

