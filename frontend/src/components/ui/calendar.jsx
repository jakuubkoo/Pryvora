import * as React from "react"
import { DayPicker } from "react-day-picker"
import "react-day-picker/dist/style.css"

import { cn } from "@/lib/utils"

function Calendar({
  className,
  classNames,
  ...props
}) {
  return (
    <DayPicker
      className={cn("p-3", className)}
      classNames={{
        months: "flex flex-col sm:flex-row gap-4",
        month: "space-y-4",
        caption: "flex justify-center pt-1 relative items-center",
        caption_label: "text-sm font-medium text-white",
        nav: "space-x-1 flex items-center",
        nav_button: "h-7 w-7 bg-transparent p-0 opacity-70 hover:opacity-100 hover:bg-white/10 rounded-md cursor-pointer transition-all",
        nav_button_previous: "absolute left-1",
        nav_button_next: "absolute right-1",
        table: "w-full border-collapse space-y-1",
        head_row: "flex",
        head_cell: "text-[#888888] rounded-md w-9 font-normal text-xs",
        row: "flex w-full mt-2",
        cell: "relative p-0 text-center text-sm",
        day: "h-9 w-9 p-0 font-normal text-white hover:bg-white/10 rounded-md cursor-pointer transition-all",
        day_selected: "bg-indigo-600 text-white hover:bg-indigo-600 rounded-md",
        day_today: "ring-1 ring-indigo-500 text-white rounded-md",
        day_outside: "text-[#555555] opacity-50",
        day_disabled: "text-[#555555] opacity-50 cursor-not-allowed",
        day_hidden: "invisible",
        ...classNames,
      }}
      {...props}
    />
  )
}
Calendar.displayName = "Calendar"

export { Calendar }

