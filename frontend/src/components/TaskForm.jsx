import { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import {
  CircleDashedIcon,
  CircleDotIcon,
  CheckCircle2Icon,
  XIcon,
} from 'lucide-react'
import { addDays, addWeeks, addMonths, format } from 'date-fns'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { cn } from '@/lib/utils'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import DatePicker from '@/components/DatePicker'

const quick_date_options = [
  { label: 'Today', getValue: () => new Date() },
  { label: 'Tomorrow', getValue: () => addDays(new Date(), 1) },
  { label: 'In 3 days', getValue: () => addDays(new Date(), 3) },
  { label: 'In a week', getValue: () => addWeeks(new Date(), 1) },
  { label: 'In a month', getValue: () => addMonths(new Date(), 1) },
]

const priority_options = [
  { value: 'low', label: 'Low', color: 'var(--sage)' },
  { value: 'medium', label: 'Medium', color: 'var(--gold)' },
  { value: 'high', label: 'High', color: 'var(--clay)' },
]

const tint = (color, percent) =>
{
  return `color-mix(in srgb, ${color} ${percent}%, transparent)`
}

const underline_field = [
  'rounded-none border-0 border-b border-line bg-transparent px-0 text-ink',
  'placeholder:text-faint shadow-none',
  'focus-visible:border-accent focus-visible:ring-0',
].join(' ')

export default function TaskForm({ task, on_submit, on_cancel })
{
  const should_reduce_motion = use_reduced_motion()

  const [form_data, set_form_data] = useState({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    due_date: '',
  })

  useEffect(() =>
  {
    if (task)
    {
      set_form_data({
        title: task.title || '',
        description: task.description || '',
        status: task.status || 'todo',
        priority: task.priority || 'medium',
        due_date: task.due_date ? task.due_date.split(' ')[0] : '',
      })
    }
  }, [task])

  const handle_submit = (e) =>
  {
    e.preventDefault()

    const submit_data = {
      ...form_data,
      due_date: form_data.due_date ? `${form_data.due_date} 00:00:00` : null,
    }

    on_submit(submit_data)
  }

  const handle_date_change = (value) =>
  {
    handle_change('due_date', value)
  }

  const handle_change = (field, value) =>
  {
    set_form_data(prev => ({ ...prev, [field]: value }))
  }

  const handle_quick_date = (get_date) =>
  {
    const date = get_date()
    const formatted = format(date, 'yyyy-MM-dd')
    handle_change('due_date', formatted)
  }

  return (
    <Dialog open={true} onOpenChange={on_cancel}>
      <DialogContent className="panel max-h-[90vh] overflow-y-auto sm:max-w-[540px]">
        <DialogHeader className="space-y-2 text-left">
          <span className="eyebrow">{task ? 'Edit entry' : 'New entry'}</span>
          <DialogTitle className="text-xl font-bold tracking-[-0.02em] text-ink">
            {task ? 'Edit task' : 'Create a task'}
          </DialogTitle>
          <DialogDescription className="text-sm text-dim">
            {task ? 'Update the details of your task below.' : 'A title is all you need. The rest can wait.'}
          </DialogDescription>
        </DialogHeader>

        <motion.form
          onSubmit={handle_submit}
          className="mt-2 space-y-6"
          initial={{ opacity: should_reduce_motion ? 1 : 0, y: should_reduce_motion ? 0 : 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: should_reduce_motion ? 0 : 0.3, ease: [0.22, 1, 0.36, 1] }}
        >
          {/* Title */}
          <div className="space-y-2">
            <Label htmlFor="title" className="eyebrow">
              Title <span className="text-down">*</span>
            </Label>
            <Input
              id="title"
              value={form_data.title}
              onChange={(e) => handle_change('title', e.target.value)}
              placeholder="e.g. Complete project proposal"
              required
              autoFocus
              className={cn(underline_field, 'text-base')}
            />
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="description" className="eyebrow">
              Description
            </Label>
            <Textarea
              id="description"
              value={form_data.description}
              onChange={(e) => handle_change('description', e.target.value)}
              placeholder="Add more details about this task..."
              rows={4}
              className={cn(underline_field, 'resize-none text-sm')}
            />
          </div>

          {/* Status + priority */}
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="status" className="eyebrow">
                Status
              </Label>
              <Select value={form_data.status} onValueChange={(value) => handle_change('status', value)}>
                <SelectTrigger
                  id="status"
                  className="w-full cursor-pointer rounded-[11px] border-line bg-panel-sunk text-ink"
                >
                  <SelectValue/>
                </SelectTrigger>
                <SelectContent className="panel">
                  <SelectItem value="todo" className="cursor-pointer text-ink">
                    <span className="flex items-center gap-2">
                      <CircleDashedIcon className="size-4" style={{ color: 'var(--sage)' }}/>
                      To Do
                    </span>
                  </SelectItem>
                  <SelectItem value="in_progress" className="cursor-pointer text-ink">
                    <span className="flex items-center gap-2">
                      <CircleDotIcon className="size-4" style={{ color: 'var(--gold)' }}/>
                      In Progress
                    </span>
                  </SelectItem>
                  <SelectItem value="done" className="cursor-pointer text-ink">
                    <span className="flex items-center gap-2">
                      <CheckCircle2Icon className="size-4 text-accent"/>
                      Done
                    </span>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Segmented priority control */}
            <div className="space-y-2">
              <Label className="eyebrow" id="priority_label">
                Priority
              </Label>
              <div
                role="radiogroup"
                aria-labelledby="priority_label"
                className="flex gap-1 rounded-[11px] border border-line bg-panel-sunk p-1"
              >
                {priority_options.map((option) =>
                {
                  const is_selected = form_data.priority === option.value
                  return (
                    <button
                      key={option.value}
                      type="button"
                      role="radio"
                      aria-checked={is_selected}
                      onClick={() => handle_change('priority', option.value)}
                      className={cn(
                        'flex-1 cursor-pointer rounded-[8px] px-2 py-1.5 text-xs font-semibold transition-colors duration-150',
                        !is_selected && 'text-dim hover:text-ink'
                      )}
                      style={is_selected
                        ? { color: option.color, backgroundColor: tint(option.color, 16) }
                        : undefined}
                    >
                      {option.label}
                    </button>
                  )
                })}
              </div>
            </div>
          </div>

          {/* Due date */}
          <div className="space-y-3">
            <Label className="eyebrow" id="due_date_label">
              Due date
            </Label>

            <div className="flex flex-wrap gap-2">
              {quick_date_options.map((option) =>
              {
                const is_selected = form_data.due_date === format(option.getValue(), 'yyyy-MM-dd')
                return (
                  <button
                    key={option.label}
                    type="button"
                    aria-pressed={is_selected}
                    onClick={() => handle_quick_date(option.getValue)}
                    className={cn(
                      'cursor-pointer rounded-[20px] border px-3 py-1.5 text-xs font-semibold transition-colors duration-150',
                      is_selected
                        ? 'border-transparent bg-[color-mix(in_srgb,var(--accent)_14%,transparent)] font-bold text-accent'
                        : 'border-line text-dim hover:border-line hover:text-ink'
                    )}
                  >
                    {option.label}
                  </button>
                )
              })}

              {form_data.due_date && (
                <button
                  type="button"
                  onClick={() => handle_change('due_date', '')}
                  aria-label="Clear due date"
                  className="flex cursor-pointer items-center gap-1 rounded-[20px] border border-line px-3 py-1.5 text-xs font-semibold text-dim transition-colors duration-150 hover:border-down hover:text-down"
                >
                  <XIcon className="size-3"/>
                  Clear
                </button>
              )}
            </div>

            <DatePicker
              value={form_data.due_date}
              on_change={handle_date_change}
              placeholder="Or pick a specific date"
            />
          </div>

          <DialogFooter className="gap-2 pt-2 sm:gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={on_cancel}
              className="min-w-[100px] cursor-pointer rounded-[11px]"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="min-w-[100px] cursor-pointer rounded-[11px] bg-accent text-on-accent hover:bg-[color-mix(in_srgb,var(--ink)_12%,var(--accent))]"
            >
              {task ? 'Save changes' : 'Create task'}
            </Button>
          </DialogFooter>
        </motion.form>
      </DialogContent>
    </Dialog>
  )
}
