import { useState } from 'react'
import { motion } from 'framer-motion'
import { Trash2Icon, EditIcon, CalendarIcon, AlertCircleIcon, AlertTriangle } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

const priority_colors = {
  low: 'bg-slate-800 text-slate-400 border border-slate-600',
  medium: 'bg-amber-900/60 text-amber-300 border border-amber-600',
  high: 'bg-rose-900/60 text-rose-300 border border-rose-600',
}

const status_colors = {
  todo: 'bg-zinc-700 text-zinc-200 border border-zinc-500',
  in_progress: 'bg-indigo-950 text-indigo-300 border border-indigo-700',
  done: 'bg-emerald-950 text-emerald-400 border border-emerald-700 line-through opacity-60',
}

const status_border_colors = {
  todo: 'border-l-slate-500/50',
  in_progress: 'border-l-indigo-500/50',
  done: 'border-l-green-500/50',
}

const capitalize_text = (text) =>
{
  return text
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

export default function TaskItem({ task, on_toggle_status, on_edit, on_delete })
{
  const [deleting, set_deleting] = useState(false)
  const [show_delete_modal, set_show_delete_modal] = useState(false)

  const handle_delete_click = () =>
  {
    set_show_delete_modal(true)
  }

  const handle_delete_confirm = async () =>
  {
    set_deleting(true)
    set_show_delete_modal(false)
    await on_delete(task.id)
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
        'group relative flex flex-col justify-between p-4 rounded-lg border-l-4 border border-white/10 transition-all duration-150 cursor-pointer h-48',
        'bg-zinc-900',
        'hover:bg-zinc-800 hover:border-white/20',
        status_border_colors[task.status],
        task.status === 'done' && 'opacity-50',
        deleting && 'opacity-30 pointer-events-none'
      )}
      transition={{ duration: 0.15 }}
    >
      {/* Header: Checkbox + Title + Actions */}
      <div className="flex items-start gap-3 mb-3">
        <Checkbox
          checked={task.status === 'done'}
          onCheckedChange={() => on_toggle_status(task)}
          className="transition-all cursor-pointer mt-0.5 border-zinc-600 data-[state=checked]:bg-indigo-600 data-[state=checked]:border-indigo-600"
        />

        <div className="flex-1 min-w-0">
          <h3
            className={cn(
              'text-sm font-semibold text-white leading-snug',
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
            onClick={handle_delete_click}
            className="text-[#888888] hover:text-red-400 hover:bg-red-500/10 cursor-pointer"
          >
            <Trash2Icon className="size-4"/>
          </Button>
        </div>
      </div>

      {/* Description */}
      {task.description && (
        <p className="text-xs text-zinc-400 leading-relaxed whitespace-pre-wrap mb-auto line-clamp-3">
          {task.description}
        </p>
      )}

      {/* Footer: Badges */}
      <div className="flex flex-wrap items-center gap-2 mt-auto pt-3">
        <Badge
          className={cn('text-xs font-medium px-2 py-0.5 rounded-md cursor-default', status_colors[task.status])}
        >
          {capitalize_text(task.status)}
        </Badge>
        <Badge
          className={cn('text-xs font-medium px-2 py-0.5 rounded-md cursor-default', priority_colors[task.priority])}
        >
          {capitalize_text(task.priority)}
        </Badge>
        {task.due_date && (
          <div className="text-xs text-zinc-400 flex items-center gap-1">
            {is_overdue() ? (
              <AlertCircleIcon className="size-3"/>
            ) : (
              <CalendarIcon className="size-3"/>
            )}
            {format_date(task.due_date)}
          </div>
        )}
      </div>

      {/* Delete Confirmation Modal */}
      <Dialog open={show_delete_modal} onOpenChange={set_show_delete_modal}>
        <DialogContent className="sm:max-w-sm bg-[#0f0f0f] border border-white/10 rounded-xl animate-in fade-in zoom-in-95 duration-200">
          <div className="flex flex-col items-center text-center space-y-4 py-2">
            <div className="flex items-center justify-center w-12 h-12 rounded-full bg-rose-500/10">
              <AlertTriangle className="size-6 text-rose-500"/>
            </div>

            <DialogHeader className="space-y-2">
              <DialogTitle className="text-[#e5e5e5] text-xl font-bold">
                Delete Task?
              </DialogTitle>
              <DialogDescription className="text-[#888888]">
                This action cannot be undone.
              </DialogDescription>
            </DialogHeader>

            <DialogFooter className="flex flex-col sm:flex-row gap-2 w-full pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => set_show_delete_modal(false)}
                className="flex-1 border border-white/20 hover:border-white/40 cursor-pointer"
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handle_delete_confirm}
                className="flex-1 bg-rose-600 hover:bg-rose-700 text-white cursor-pointer"
              >
                Delete
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>
    </motion.div>
  )
}

