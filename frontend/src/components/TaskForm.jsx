import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  XIcon,
  CircleIcon,
  CircleDotIcon,
  CheckCircle2Icon,
  FlagIcon,
  AlertCircleIcon,
  SignalIcon
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
import DatePicker from '@/components/DatePicker'

const quick_date_options = [
  { label: 'Today', getValue: () => new Date() },
  { label: 'Tomorrow', getValue: () => addDays(new Date(), 1) },
  { label: 'In 3 days', getValue: () => addDays(new Date(), 3) },
  { label: 'In a week', getValue: () => addWeeks(new Date(), 1) },
  { label: 'In a month', getValue: () => addMonths(new Date(), 1) },
]

export default function TaskForm({ task, on_submit, on_cancel })
{
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
      <DialogContent className="border-[#1a1a1a] bg-[#0f0f0f] sm:max-w-[540px]">
        <DialogHeader className="space-y-3">
          <DialogTitle className="text-[#e5e5e5] text-xl">
            {task ? 'Edit Task' : 'Create New Task'}
          </DialogTitle>
          <DialogDescription className="text-[#888888] text-sm">
            {task ? 'Update the details of your task below.' : 'Fill in the details to create a new task.'}
          </DialogDescription>
        </DialogHeader>

        <motion.form
          onSubmit={handle_submit}
          className="space-y-5 mt-2"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
        >
          {/* Title */}
          <div className="space-y-2">
            <Label htmlFor="title" className="text-[#e5e5e5] text-sm font-medium">
              Title <span className="text-red-400">*</span>
            </Label>
            <Input
              id="title"
              value={form_data.title}
              onChange={(e) => handle_change('title', e.target.value)}
              placeholder="e.g., Complete project proposal"
              required
              autoFocus
              className="bg-[#0a0a0a] border-[#1a1a1a] text-[#e5e5e5] placeholder:text-[#555555] focus:border-[#2a2a2a]"
            />
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="description" className="text-[#e5e5e5] text-sm font-medium">
              Description
            </Label>
            <Textarea
              id="description"
              value={form_data.description}
              onChange={(e) => handle_change('description', e.target.value)}
              placeholder="Add more details about this task..."
              rows={4}
              className="bg-[#0a0a0a] border-[#1a1a1a] text-[#e5e5e5] placeholder:text-[#555555] focus:border-[#2a2a2a] resize-none"
            />
          </div>

          {/* Status and Priority */}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="status" className="text-[#e5e5e5] text-sm font-medium">
                Status
              </Label>
              <Select value={form_data.status} onValueChange={(value) => handle_change('status', value)}>
                <SelectTrigger className="bg-[#0a0a0a] border-[#1a1a1a] text-[#e5e5e5] focus:border-[#2a2a2a] cursor-pointer">
                  <SelectValue/>
                </SelectTrigger>
                <SelectContent className="bg-[#0f0f0f] border-[#1a1a1a]">
                  <SelectItem
                    value="todo"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-[#1a1a1a] focus:bg-[#1a1a1a]"
                  >
                    <div className="flex items-center gap-2">
                      <CircleIcon className="size-4 text-slate-400"/>
                      <span>To Do</span>
                    </div>
                  </SelectItem>
                  <SelectItem
                    value="in_progress"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-[#1a1a1a] focus:bg-[#1a1a1a]"
                  >
                    <div className="flex items-center gap-2">
                      <CircleDotIcon className="size-4 text-blue-400"/>
                      <span>In Progress</span>
                    </div>
                  </SelectItem>
                  <SelectItem
                    value="done"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-[#1a1a1a] focus:bg-[#1a1a1a]"
                  >
                    <div className="flex items-center gap-2">
                      <CheckCircle2Icon className="size-4 text-green-400"/>
                      <span>Done</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="priority" className="text-[#e5e5e5] text-sm font-medium">
                Priority
              </Label>
              <Select value={form_data.priority} onValueChange={(value) => handle_change('priority', value)}>
                <SelectTrigger className="bg-[#0a0a0a] border-[#1a1a1a] text-[#e5e5e5] focus:border-[#2a2a2a] cursor-pointer">
                  <SelectValue/>
                </SelectTrigger>
                <SelectContent className="bg-[#0f0f0f] border-[#1a1a1a]">
                  <SelectItem
                    value="low"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-blue-500/10 focus:bg-blue-500/10"
                  >
                    <div className="flex items-center gap-2">
                      <SignalIcon className="size-4 text-blue-400 rotate-180"/>
                      <span className="text-blue-400">Low</span>
                    </div>
                  </SelectItem>
                  <SelectItem
                    value="medium"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-yellow-500/10 focus:bg-yellow-500/10"
                  >
                    <div className="flex items-center gap-2">
                      <AlertCircleIcon className="size-4 text-yellow-400"/>
                      <span className="text-yellow-400">Medium</span>
                    </div>
                  </SelectItem>
                  <SelectItem
                    value="high"
                    className="text-[#e5e5e5] cursor-pointer hover:bg-red-500/10 focus:bg-red-500/10"
                  >
                    <div className="flex items-center gap-2">
                      <FlagIcon className="size-4 text-red-400"/>
                      <span className="text-red-400">High</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Due Date */}
          <div className="space-y-3">
            <Label htmlFor="due_date" className="text-[#e5e5e5] text-sm font-medium">
              Due Date
            </Label>

            {/* Quick Select Buttons */}
            <div className="flex flex-wrap gap-2">
              {quick_date_options.map((option) => (
                <Button
                  key={option.label}
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => handle_quick_date(option.getValue)}
                  className="text-xs bg-[#0a0a0a] border-[#1a1a1a] text-[#888888] hover:text-[#e5e5e5] hover:bg-[#1a1a1a] hover:border-[#2a2a2a] cursor-pointer"
                >
                  {option.label}
                </Button>
              ))}
              {form_data.due_date && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => handle_change('due_date', '')}
                  className="text-xs bg-[#0a0a0a] border-[#1a1a1a] text-red-400 hover:text-red-300 hover:bg-red-500/10 hover:border-red-500/20 cursor-pointer"
                >
                  Clear
                </Button>
              )}
            </div>

            {/* Date Picker */}
            <DatePicker
              value={form_data.due_date}
              on_change={handle_date_change}
              placeholder="Or pick a specific date"
            />
          </div>

          {/* Footer */}
          <DialogFooter className="gap-2 sm:gap-0 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={on_cancel}
              className="min-w-[100px] cursor-pointer"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="min-w-[100px] cursor-pointer"
            >
              {task ? 'Update Task' : 'Create Task'}
            </Button>
          </DialogFooter>
        </motion.form>
      </DialogContent>
    </Dialog>
  )
}

