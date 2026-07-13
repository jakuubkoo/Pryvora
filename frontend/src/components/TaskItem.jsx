import { useState } from 'react'
import { motion } from 'framer-motion'
import {
  Trash2Icon,
  PencilIcon,
  CalendarIcon,
  AlertCircleIcon,
  AlertTriangleIcon,
  CircleDashedIcon,
  CircleDotIcon,
  CheckCircle2Icon,
} from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

// One accent, one categorical set. Status and priority never invent new hues.
const status_meta = {
  todo: { label: 'To Do', color: 'var(--sage)', icon: CircleDashedIcon },
  in_progress: { label: 'In Progress', color: 'var(--gold)', icon: CircleDotIcon },
  done: { label: 'Done', color: 'var(--accent)', icon: CheckCircle2Icon },
}

const priority_meta = {
  low: { label: 'Low', color: 'var(--sage)' },
  medium: { label: 'Medium', color: 'var(--gold)' },
  high: { label: 'High', color: 'var(--clay)' },
}

const tint = (color, percent) =>
{
  return `color-mix(in srgb, ${color} ${percent}%, transparent)`
}

export default function TaskItem({ task, on_toggle_status, on_edit, on_delete })
{
  const [deleting, set_deleting] = useState(false)
  const [show_delete_modal, set_show_delete_modal] = useState(false)

  const status = status_meta[task.status] || status_meta.todo
  const priority = priority_meta[task.priority] || priority_meta.low
  const StatusIcon = status.icon

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

  const overdue = is_overdue()
  const due_color = overdue ? 'var(--down)' : 'var(--dim)'

  return (
    <motion.div
      className={cn(
        'panel group relative flex h-full min-h-[11.5rem] flex-col overflow-hidden p-4 transition-colors duration-150',
        'hover:bg-[color-mix(in_srgb,var(--ink)_3%,var(--panel))]',
        deleting && 'pointer-events-none opacity-40'
      )}
      transition={{ duration: 0.15 }}
    >
      {/* Colour-coded left edge, by status */}
      <span
        aria-hidden="true"
        className="pointer-events-none absolute inset-y-0 left-0 w-[3px]"
        style={{ backgroundColor: status.color }}
      />

      {/* Header: checkbox + title + actions */}
      <div className="mb-2 flex items-start gap-3 pl-1">
        <Checkbox
          checked={task.status === 'done'}
          onCheckedChange={() => on_toggle_status(task)}
          aria-label={task.status === 'done' ? `Mark "${task.title}" as not done` : `Mark "${task.title}" as done`}
          className="mt-0.5 cursor-pointer border-line2 data-[state=checked]:border-accent data-[state=checked]:bg-accent"
        />

        <h3
          className={cn(
            'min-w-0 flex-1 text-sm font-semibold leading-snug text-ink',
            task.status === 'done' && 'text-dim line-through'
          )}
        >
          {task.title}
        </h3>

        <div className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            onClick={on_edit}
            aria-label={`Edit task "${task.title}"`}
            className="cursor-pointer text-faint hover:bg-panel-sunk hover:text-ink"
          >
            <PencilIcon className="size-4"/>
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            onClick={handle_delete_click}
            aria-label={`Delete task "${task.title}"`}
            className="cursor-pointer text-faint hover:bg-[color-mix(in_srgb,var(--down)_12%,transparent)] hover:text-down"
          >
            <Trash2Icon className="size-4"/>
          </Button>
        </div>
      </div>

      {/* Description */}
      {task.description && (
        <p className="mb-auto line-clamp-3 whitespace-pre-wrap pl-1 text-xs leading-relaxed text-dim">
          {task.description}
        </p>
      )}

      {/* Footer: status, priority, due date */}
      <div className="mt-auto flex flex-wrap items-center gap-1.5 pl-1 pt-3">
        <span
          className="num inline-flex items-center gap-1 rounded-[20px] border px-2 py-0.5 text-[11px] font-semibold"
          style={{
            color: status.color,
            backgroundColor: tint(status.color, 13),
            borderColor: tint(status.color, 30),
          }}
        >
          <StatusIcon className="size-3"/>
          {status.label}
        </span>

        <span
          className="num inline-flex items-center rounded-[20px] border px-2 py-0.5 text-[11px] font-semibold"
          style={{
            color: priority.color,
            backgroundColor: tint(priority.color, 13),
            borderColor: tint(priority.color, 30),
          }}
        >
          {priority.label}
        </span>

        {task.due_date && (
          <span
            className="num inline-flex items-center gap-1 text-[11px] font-medium"
            style={{ color: due_color }}
          >
            {overdue ? <AlertCircleIcon className="size-3"/> : <CalendarIcon className="size-3"/>}
            {format_date(task.due_date)}
          </span>
        )}
      </div>

      {/* Delete confirmation */}
      <Dialog open={show_delete_modal} onOpenChange={set_show_delete_modal}>
        <DialogContent className="panel sm:max-w-sm">
          <div className="flex flex-col items-center space-y-4 py-2 text-center">
            <div
              className="flex size-12 items-center justify-center rounded-[20px]"
              style={{ backgroundColor: tint('var(--down)', 13) }}
            >
              <AlertTriangleIcon className="size-6 text-down"/>
            </div>

            <DialogHeader className="space-y-2">
              <DialogTitle className="text-xl font-bold text-ink">
                Delete this task?
              </DialogTitle>
              <DialogDescription className="text-dim">
                &ldquo;{task.title}&rdquo; will be removed from the archive. This cannot be undone.
              </DialogDescription>
            </DialogHeader>

            <DialogFooter className="flex w-full flex-col gap-2 pt-2 sm:flex-row">
              <Button
                type="button"
                variant="outline"
                onClick={() => set_show_delete_modal(false)}
                className="flex-1 cursor-pointer"
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={handle_delete_confirm}
                className="flex-1 cursor-pointer border text-on-accent"
                style={{ backgroundColor: 'var(--down)', borderColor: 'var(--down)' }}
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
