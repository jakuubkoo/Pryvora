import { useState } from 'react'
import { motion } from 'framer-motion'
import { Trash2Icon, EditIcon, CalendarIcon, AlertCircleIcon } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

const priority_colors = {
  low: 'bg-blue-500/10 text-blue-400 border-blue-500/20 hover:bg-blue-500/20',
  medium: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20 hover:bg-yellow-500/20',
  high: 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20',
}

const status_colors = {
  todo: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
  in_progress: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  done: 'bg-green-500/10 text-green-400 border-green-500/20',
}

export default function TaskItem({ task, on_toggle_status, on_edit, on_delete })
{
  const [deleting, set_deleting] = useState(false)

  const handle_delete = async () =>
  {
    if (window.confirm('Are you sure you want to delete this task?'))
    {
      set_deleting(true)
      await on_delete(task.id)
    }
  }

  const format_date = (date_string) =>
  {
    if (!date_string)
    {
      return null
    }
    const date = new Date(date_string)
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  }

  const is_overdue = () =>
  {
    if (!task.due_date || task.status === 'done')
    {
      return false
    }
    const today = new Date().toISOString().split('T')[0]
    return task.due_date.split(' ')[0] < today
  }

  return (
    <motion.div
      className={cn(
        'group relative flex items-start gap-4 p-5 rounded-lg border transition-all duration-300 cursor-pointer',
        'border-[#1a1a1a] bg-[#0a0a0a]',
        'hover:border-[#2a2a2a] hover:bg-[#111111] hover:shadow-lg hover:shadow-black/20',
        task.status === 'done' && 'opacity-50',
        deleting && 'opacity-30 pointer-events-none'
      )}
      whileHover={{ y: -4, scale: 1.01 }}
      transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
    >
      {/* Checkbox */}
      <div className="pt-0.5">
        <Checkbox
          checked={task.status === 'done'}
          onCheckedChange={() => on_toggle_status(task)}
          className="transition-all cursor-pointer"
        />
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0 space-y-3">
        {/* Title and Actions */}
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <h3
              className={cn(
                'text-base font-medium text-[#e5e5e5] leading-snug',
                task.status === 'done' && 'line-through text-[#666666]'
              )}
            >
              {task.title}
            </h3>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <Button
              variant="ghost"
              size="icon-sm"
              onClick={on_edit}
              className="text-[#888888] hover:text-[#e5e5e5] hover:bg-[#1a1a1a] cursor-pointer"
            >
              <EditIcon className="size-4"/>
            </Button>
            <Button
              variant="ghost"
              size="icon-sm"
              onClick={handle_delete}
              className="text-[#888888] hover:text-red-400 hover:bg-red-500/10 cursor-pointer"
            >
              <Trash2Icon className="size-4"/>
            </Button>
          </div>
        </div>

        {/* Description */}
        {task.description && (
          <p className="text-sm text-[#888888] leading-relaxed whitespace-pre-wrap">
            {task.description}
          </p>
        )}

        {/* Badges */}
        <div className="flex flex-wrap items-center gap-2">
          <Badge
            variant="outline"
            className={cn('text-xs font-medium transition-colors cursor-default', status_colors[task.status])}
          >
            {task.status.replace('_', ' ')}
          </Badge>
          <Badge
            variant="outline"
            className={cn('text-xs font-medium transition-colors cursor-default', priority_colors[task.priority])}
          >
            {task.priority}
          </Badge>
          {task.due_date && (
            <Badge
              variant="outline"
              className={cn(
                'text-xs font-medium gap-1.5 transition-colors cursor-default',
                is_overdue()
                  ? 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500/20'
                  : 'bg-slate-500/10 text-slate-400 border-slate-500/20 hover:bg-slate-500/20'
              )}
            >
              {is_overdue() ? (
                <AlertCircleIcon className="size-3"/>
              ) : (
                <CalendarIcon className="size-3"/>
              )}
              {format_date(task.due_date)}
            </Badge>
          )}
        </div>
      </div>
    </motion.div>
  )
}

