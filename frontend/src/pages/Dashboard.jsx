import { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import { PlusIcon, CheckCircle2Icon, CalendarIcon, FileTextIcon, CheckSquareIcon } from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Input } from '@/components/ui/input'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { cn } from '@/lib/utils'

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

const get_section_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.25,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

export default function Dashboard()
{
  const { user, api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()
  const [dashboard_data, set_dashboard_data] = useState(null)
  const [loading, set_loading] = useState(true)
  const [quick_task_title, set_quick_task_title] = useState('')
  const [adding_task, set_adding_task] = useState(false)

  const page_variants = get_page_variants(should_reduce_motion)
  const section_variants = get_section_variants(should_reduce_motion)

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

  const get_formatted_date = () =>
  {
    const today = new Date()
    return today.toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    })
  }

  const fetch_dashboard_data = async () =>
  {
    set_loading(true)
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/dashboard/`)
      if (response.ok)
      {
        const data = await response.json()
        set_dashboard_data(data)
      }
    }
    catch (error)
    {
      console.error('Failed to fetch dashboard data:', error)
    }
    finally
    {
      set_loading(false)
    }
  }

  const handle_quick_add_task = async (e) =>
  {
    e.preventDefault()
    if (!quick_task_title.trim() || adding_task)
    {
      return
    }

    set_adding_task(true)
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/task/`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          title: quick_task_title.trim(),
          status: 'todo',
          priority: 'medium',
        }),
      })

      if (response.ok)
      {
        set_quick_task_title('')
        fetch_dashboard_data()
      }
    }
    catch (error)
    {
      console.error('Failed to create task:', error)
    }
    finally
    {
      set_adding_task(false)
    }
  }

  const handle_toggle_task = async (task) =>
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

  const get_user_name = () =>
  {
    if (user?.first_name)
    {
      return user.first_name
    }
    if (!user?.email)
    {
      return 'there'
    }
    const name = user.email.split('@')[0]
    const clean_name = name.replace(/[0-9]/g, '').split(/[._-]/)[0]
    return clean_name.charAt(0).toUpperCase() + clean_name.slice(1)
  }

  const group_upcoming_tasks = (tasks) =>
  {
    if (!tasks || !Array.isArray(tasks))
    {
      return []
    }

    const grouped = {}
    const today = new Date()
    const next_week = new Date(today)
    next_week.setDate(today.getDate() + 7)

    tasks.forEach(task => {
      if (!task.due_date)
      {
        return
      }

      const due_date = new Date(task.due_date.split(' ')[0])
      if (due_date > next_week)
      {
        return
      }

      const date_key = task.due_date.split(' ')[0]
      if (!grouped[date_key])
      {
        grouped[date_key] = []
      }
      grouped[date_key].push(task)
    })

    return Object.entries(grouped).sort((a, b) => a[0].localeCompare(b[0]))
  }

  const get_task_counts = () =>
  {
    if (!dashboard_data)
    {
      return { today: 0, upcoming: 0, notes: 0 }
    }

    const today_count = dashboard_data?.tasks?.today?.length || 0
    const upcoming_count = dashboard_data?.tasks?.upcoming?.length || 0
    const notes_count = dashboard_data?.recent_notes?.length || 0

    return { today: today_count, upcoming: upcoming_count, notes: notes_count }
  }

  const format_date_header = (date_string) =>
  {
    const date = new Date(date_string)
    const today = new Date()
    const tomorrow = new Date(today)
    tomorrow.setDate(today.getDate() + 1)

    if (date.toDateString() === today.toDateString())
    {
      return 'Today'
    }
    if (date.toDateString() === tomorrow.toDateString())
    {
      return 'Tomorrow'
    }

    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })
  }

  const format_task_date = (date_string) =>
  {
    if (!date_string)
    {
      return null
    }
    const date = new Date(date_string.split(' ')[0])
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  const is_task_overdue = (task) =>
  {
    if (!task.due_date || task.status === 'done')
    {
      return false
    }
    const today = new Date().toISOString().split('T')[0]
    return task.due_date.split(' ')[0] < today
  }

  useEffect(() =>
  {
    fetch_dashboard_data()
  }, [])

  const get_task_border_color = (task, is_overdue) =>
  {
    if (is_overdue)
    {
      return 'border-l-red-500'
    }
    if (task.status === 'done')
    {
      return 'border-l-green-500'
    }
    if (task.status === 'in_progress')
    {
      return 'border-l-orange-500'
    }
    return 'border-l-blue-500'
  }

  const TaskRow = ({ task, is_overdue, show_date = true }) => (
    <motion.div
      className={cn(
        'group flex items-center gap-3 px-4 py-3 rounded-lg border border-white/8 border-l-2 bg-[#1a1a1a] transition-all duration-150',
        'hover:border-white/15 hover:bg-[#222222]',
        is_overdue && 'bg-red-500/10 border-red-500/30 hover:border-red-500/40 hover:bg-red-500/15',
        task.status === 'done' && 'opacity-50',
        get_task_border_color(task, is_overdue)
      )}
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
    >
      <button
        onClick={() => handle_toggle_task(task)}
        className={cn(
          'flex-shrink-0 w-4 h-4 rounded border transition-all',
          task.status === 'done'
            ? 'bg-indigo-600 border-indigo-600'
            : 'border-white/20 hover:border-white/40'
        )}
      >
        {task.status === 'done' && (
          <CheckCircle2Icon className="w-4 h-4 text-white"/>
        )}
      </button>

      <span className={cn(
        'flex-1 text-sm text-[#e5e5e5]',
        task.status === 'done' && 'line-through text-[#666666]'
      )}>
        {task.title}
      </span>

      {task.priority && (
        <div className={cn(
          'w-1.5 h-1.5 rounded-full flex-shrink-0',
          task.priority === 'high' && 'bg-red-500',
          task.priority === 'medium' && 'bg-amber-500',
          task.priority === 'low' && 'bg-slate-500'
        )}/>
      )}

      {show_date && task.due_date && (
        <span className={cn(
          'text-xs flex-shrink-0',
          is_overdue ? 'text-red-400' : 'text-[#666666]'
        )}>
          {format_task_date(task.due_date)}
        </span>
      )}
    </motion.div>
  )

  const NoteCard = ({ note }) => (
    <motion.div
      className="group p-5 rounded-lg border border-white/8 bg-[#1a1a1a] hover:border-white/15 hover:bg-[#222222] transition-all duration-150 cursor-pointer"
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
    >
      <h4 className="text-sm font-medium text-[#e5e5e5] mb-2 line-clamp-1">
        {note.title}
      </h4>
      <p className="text-xs text-[#888888] line-clamp-2 leading-relaxed">
        {note.content || 'No content'}
      </p>
    </motion.div>
  )

  return (
    <AppLayout title="Dashboard">
      <motion.div
        className="p-8 space-y-8 max-w-[1600px] mx-auto min-h-screen"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        {/* Greeting Section */}
        <motion.div
          className="space-y-3"
          variants={section_variants}
        >
          <h1 className="text-3xl font-semibold text-[#e5e5e5]">
            {get_greeting()}, {get_user_name()}
          </h1>
          <p className="text-sm text-[#666666]">
            {get_formatted_date()}
          </p>
          {!loading && (
            <div className="flex items-center gap-3 pt-2">
              <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/20">
                <CheckSquareIcon className="w-3.5 h-3.5 text-[#e5e5e5]"/>
                <span className="text-sm font-medium text-[#e5e5e5]">
                  {get_task_counts().today} today
                </span>
              </div>
              <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/20">
                <CalendarIcon className="w-3.5 h-3.5 text-[#e5e5e5]"/>
                <span className="text-sm font-medium text-[#e5e5e5]">
                  {get_task_counts().upcoming} upcoming
                </span>
              </div>
              <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/20">
                <FileTextIcon className="w-3.5 h-3.5 text-[#e5e5e5]"/>
                <span className="text-sm font-medium text-[#e5e5e5]">
                  {get_task_counts().notes} {get_task_counts().notes === 1 ? 'note' : 'notes'}
                </span>
              </div>
            </div>
          )}
        </motion.div>

        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="text-[#666666]">Loading...</div>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left Column - Tasks (2/3 width) */}
            <div className="lg:col-span-2 space-y-8">
              {/* Overdue Tasks */}
              <motion.div
                key="overdue-section"
                className="space-y-4"
                variants={section_variants}
                initial="hidden"
                animate="visible"
              >
                <h2 className="text-sm font-semibold text-red-400 uppercase tracking-wide">
                  Overdue
                </h2>
                {dashboard_data?.tasks?.overdue?.length > 0 ? (
                  <div className="space-y-2">
                    {dashboard_data.tasks.overdue.map(task => (
                      <TaskRow key={task.id} task={task} is_overdue={true}/>
                    ))}
                  </div>
                ) : (
                  <div className="px-4 py-6 rounded-lg border border-white/8 bg-[#1a1a1a]">
                    <p className="text-xs text-[#888888]">No overdue tasks</p>
                  </div>
                )}
              </motion.div>

              {/* Today's Tasks */}
              <motion.div
                key="today-section"
                className="space-y-4"
                variants={section_variants}
                initial="hidden"
                animate="visible"
              >
                <h2 className="text-sm font-semibold text-[#f5f5f5] uppercase tracking-wide">
                  Today
                </h2>

                {/* Quick Add Input */}
                <form onSubmit={handle_quick_add_task}>
                  <div className="flex items-center gap-3 px-4 py-3 rounded-lg border border-white/8 bg-[#1a1a1a] hover:border-white/15 hover:bg-[#222222] transition-all duration-150 group">
                    <PlusIcon className="w-4 h-4 text-[#888888] group-hover:text-[#e5e5e5] transition-colors flex-shrink-0"/>
                    <Input
                      type="text"
                      placeholder="Add a task for today..."
                      value={quick_task_title}
                      onChange={(e) => set_quick_task_title(e.target.value)}
                      disabled={adding_task}
                      className={cn(
                        'border-0 bg-transparent text-[#e5e5e5] placeholder:text-[#666666] p-0 h-auto',
                        'focus-visible:ring-0 focus-visible:ring-offset-0',
                        'transition-all duration-200'
                      )}
                    />
                  </div>
                </form>

                {dashboard_data?.tasks?.today?.length > 0 ? (
                  <div className="space-y-2">
                    {dashboard_data.tasks.today.map(task => (
                      <TaskRow key={task.id} task={task} is_overdue={false} show_date={false}/>
                    ))}
                  </div>
                ) : (
                  <div className="px-4 py-6 rounded-lg border border-white/8 bg-[#1a1a1a]">
                    <p className="text-xs text-[#888888]">No tasks for today</p>
                  </div>
                )}
              </motion.div>

              {/* Upcoming Tasks (Next 7 Days) */}
              <motion.div
                key="upcoming-section"
                className="space-y-4"
                variants={section_variants}
                initial="hidden"
                animate="visible"
              >
                <h2 className="text-sm font-semibold text-[#f5f5f5] uppercase tracking-wide">
                  Upcoming
                </h2>
                {dashboard_data?.tasks?.upcoming?.length > 0 ? (
                  <div className="space-y-4">
                    {group_upcoming_tasks(dashboard_data.tasks.upcoming).length > 0 ? (
                      group_upcoming_tasks(dashboard_data.tasks.upcoming).map(([date, tasks]) => (
                        <div key={date} className="space-y-3">
                          <h3 className="text-xs font-medium text-[#888888] px-4">
                            {format_date_header(date)}
                          </h3>
                          <div className="space-y-2">
                            {tasks.map(task => (
                              <TaskRow key={task.id} task={task} is_overdue={false} show_date={true}/>
                            ))}
                          </div>
                        </div>
                      ))
                    ) : (
                      <div className="px-4 py-6 rounded-lg border border-white/8 bg-[#1a1a1a]">
                        <p className="text-xs text-[#888888]">No upcoming tasks in the next 7 days</p>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="px-4 py-6 rounded-lg border border-white/8 bg-[#1a1a1a]">
                    <p className="text-xs text-[#888888]">No upcoming tasks</p>
                  </div>
                )}
              </motion.div>
            </div>

            {/* Right Column - Notes (1/3 width) */}
            <motion.div
              className="space-y-6 lg:sticky lg:top-6 lg:self-start"
              variants={section_variants}
              initial="hidden"
              animate="visible"
            >
              {/* Recent Notes */}
              <div className="space-y-4">
                <h2 className="text-sm font-semibold text-[#f5f5f5] uppercase tracking-wide">
                  Recent Notes
                </h2>
                {dashboard_data?.recent_notes?.length > 0 ? (
                  <div className="grid grid-cols-2 gap-3">
                    {dashboard_data.recent_notes.map(note => (
                      <NoteCard key={note.id} note={note}/>
                    ))}
                  </div>
                ) : (
                  <div className="p-6 rounded-lg border border-white/8 bg-[#1a1a1a] text-center">
                    <p className="text-xs text-[#888888]">No notes yet</p>
                  </div>
                )}
              </div>
            </motion.div>
          </div>
        )}
      </motion.div>
    </AppLayout>
  )
}

