import { useState } from 'react'
import { CalendarIcon } from 'lucide-react'
import { format } from 'date-fns'
import { Calendar } from '@/components/ui/calendar'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

// The shadcn calendar primitive still carries its own defaults; re-tint it onto
// the paper tokens here rather than editing the shared primitive.
const calendar_class_names = {
  caption_label: 'text-sm font-semibold text-ink',
  nav_button: 'h-7 w-7 rounded-[9px] bg-transparent p-0 text-dim transition-colors hover:bg-panel-sunk hover:text-ink cursor-pointer',
  head_cell: 'eyebrow w-9 rounded-[9px] font-bold',
  day: 'num h-9 w-9 rounded-[9px] p-0 font-medium text-ink transition-colors hover:bg-panel-sunk cursor-pointer',
  day_selected: 'bg-accent text-on-accent hover:bg-accent rounded-[9px]',
  day_today: 'rounded-[9px] border border-accent text-accent',
  day_outside: 'text-faint opacity-60',
  day_disabled: 'text-faint opacity-40 cursor-not-allowed',
}

export default function DatePicker({ value, on_change, placeholder = 'Pick a date' })
{
  const [open, set_open] = useState(false)

  let selected_date = undefined
  if (value)
  {
    try
    {
      selected_date = new Date(value)
      if (isNaN(selected_date.getTime()))
      {
        selected_date = undefined
      }
    }
    catch
    {
      selected_date = undefined
    }
  }

  const handle_select = (date) =>
  {
    if (date)
    {
      const formatted = format(date, 'yyyy-MM-dd')
      on_change(formatted)
      set_open(false)
    }
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        onClick={() => set_open(true)}
        aria-label={selected_date ? `Due date ${format(selected_date, 'PPP')}. Change date` : placeholder}
        className={cn(
          'w-full cursor-pointer justify-start rounded-[11px] border-line bg-panel-sunk text-left font-normal text-ink transition-colors',
          'hover:bg-[color-mix(in_srgb,var(--ink)_5%,var(--panel-sunk))] hover:text-ink',
          !selected_date && 'text-faint'
        )}
      >
        <CalendarIcon className="mr-2 size-4 text-faint"/>
        {selected_date
          ? <span className="num text-ink">{format(selected_date, 'PPP')}</span>
          : <span>{placeholder}</span>}
      </Button>

      <Dialog open={open} onOpenChange={set_open}>
        <DialogContent className="panel w-[calc(100vw-2rem)] p-4 sm:max-w-[380px]">
          <DialogHeader className="space-y-1 text-left">
            <DialogTitle className="text-base font-semibold text-ink">Pick a due date</DialogTitle>
            <DialogDescription className="text-xs text-dim">
              Use the arrow keys to move between days, Enter to choose one.
            </DialogDescription>
          </DialogHeader>

          <div className="flex justify-center">
            <Calendar
              mode="single"
              selected={selected_date}
              onSelect={handle_select}
              defaultMonth={selected_date}
              initialFocus
              className="p-0"
              classNames={calendar_class_names}
            />
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
