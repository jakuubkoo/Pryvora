import { cn } from '@/lib/utils'
import { event_color, tint, format_time } from './event_utils'

/**
 * The compact event pill that lives inside a month cell.
 *
 * tabIndex is -1 by design: the month grid is a WAI-ARIA grid where arrow keys
 * move between *cells*, and Enter on a cell opens the day. Making every chip a
 * tab stop would put dozens of stops between the grid and the rest of the page.
 * The keyboard path to an event runs cell -> Enter -> day peek -> event.
 */
export default function EventChip({ event, on_open, compact = false })
{
  const color = event_color(event.id)

  return (
    <button
      type="button"
      tabIndex={-1}
      onClick={(e) =>
      {
        e.stopPropagation()
        on_open(event)
      }}
      title={event.title}
      className={cn(
        'flex w-full items-center gap-1.5 overflow-hidden rounded-[7px] px-1.5 text-left transition-opacity hover:opacity-80',
        compact ? 'py-[3px]' : 'py-1'
      )}
      style={{ background: tint(color, 13), color }}
    >
      {!event.all_day && event.starts_at && (
        <span className="num flex-none text-[10px] font-semibold opacity-80">
          {format_time(event.starts_at).replace(':00', '')}
        </span>
      )}

      {event.all_day && (
        <span
          className="h-1.5 w-1.5 flex-none rounded-full"
          style={{ background: color }}
          aria-hidden="true"
        />
      )}

      <span className="truncate text-[11.5px] font-semibold leading-tight">
        {event.title}
      </span>
    </button>
  )
}
