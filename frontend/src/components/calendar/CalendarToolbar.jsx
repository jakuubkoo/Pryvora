import { ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { cn } from '@/lib/utils'

const views = [
  { value: 'month', label: 'Month' },
  { value: 'week', label: 'Week' },
  { value: 'agenda', label: 'Agenda' },
]

/**
 * Page header: the h1 carries the period, the controls sit beside it.
 * AppLayout renders no title of its own, so this is the page's only heading.
 */
export default function CalendarToolbar({
  heading,
  sub_heading,
  view,
  on_view_change,
  on_prev,
  on_next,
  on_today,
  on_create,
})
{
  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div className="min-w-0">
        <p className="eyebrow mb-1.5">Calendar</p>
        <h1 className="truncate text-[29px] font-bold leading-tight tracking-[-0.025em]">
          {heading}
        </h1>
        <p className="num mt-1 text-[11.5px] text-dim">{sub_heading}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {/* Period nav */}
        <div className="flex items-center gap-1 rounded-[11px] border border-line p-1">
          <button
            type="button"
            onClick={on_prev}
            aria-label={`Previous ${view === 'week' ? 'week' : 'month'}`}
            className="grid h-7 w-7 place-items-center rounded-[7px] text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
          >
            <ChevronLeft className="h-4 w-4"/>
          </button>

          <button
            type="button"
            onClick={on_today}
            className="rounded-[7px] px-2.5 py-1 text-[12.5px] font-semibold text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
          >
            Today
          </button>

          <button
            type="button"
            onClick={on_next}
            aria-label={`Next ${view === 'week' ? 'week' : 'month'}`}
            className="grid h-7 w-7 place-items-center rounded-[7px] text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
          >
            <ChevronRight className="h-4 w-4"/>
          </button>
        </div>

        {/* Segmented view control */}
        <div
          role="tablist"
          aria-label="Calendar view"
          className="flex items-center gap-0.5 rounded-[11px] border border-line p-1"
        >
          {views.map(item => (
            <button
              key={item.value}
              type="button"
              role="tab"
              aria-selected={view === item.value}
              onClick={() => on_view_change(item.value)}
              className={cn(
                'rounded-[7px] px-3 py-1 text-[12.5px] transition-colors',
                view === item.value
                  ? 'font-bold text-ink'
                  : 'font-semibold text-dim hover:text-ink'
              )}
              style={
                view === item.value
                  ? { background: 'color-mix(in srgb, var(--accent) 14%, transparent)' }
                  : undefined
              }
            >
              {item.label}
            </button>
          ))}
        </div>

        <button
          type="button"
          onClick={() => on_create()}
          className="flex items-center gap-1.5 rounded-[11px] bg-accent px-3.5 py-2 text-[13px] font-bold text-on-accent transition-opacity hover:opacity-90"
        >
          <Plus className="h-4 w-4"/>
          New event
        </button>
      </div>
    </div>
  )
}
