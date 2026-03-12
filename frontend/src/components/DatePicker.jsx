import { useState } from 'react'
import { CalendarIcon, XIcon } from 'lucide-react'
import { format } from 'date-fns'
import { Calendar } from '@/components/ui/calendar'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

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
    catch (e)
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
        className={cn(
          'w-full justify-start text-left font-normal bg-[#0a0a0a] border-[#1a1a1a] text-[#e5e5e5] hover:bg-[#111111] hover:border-[#2a2a2a] cursor-pointer',
          !selected_date && 'text-[#555555]'
        )}
      >
        <CalendarIcon className="mr-2 h-4 w-4"/>
        {selected_date ? format(selected_date, 'PPP') : <span>{placeholder}</span>}
      </Button>

      <Dialog open={open} onOpenChange={set_open}>
        <DialogContent className="border-[#1a1a1a] bg-[#0f0f0f] sm:max-w-[400px] p-0">
          <div className="p-3">
            <Calendar
              mode="single"
              selected={selected_date}
              onSelect={handle_select}
              initialFocus
            />
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}

