import { CalendarPlus } from 'lucide-react'

/**
 * A month with nothing in it is the first thing a new user sees — it has to
 * read as composed and inviting, never as a broken grid.
 */
export default function EmptyMonth({ period_label, on_create })
{
  return (
    <div className="panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
      <span
        className="grid h-11 w-11 place-items-center rounded-full"
        style={{ background: 'color-mix(in srgb, var(--accent) 13%, transparent)' }}
      >
        <CalendarPlus className="h-[18px] w-[18px] text-accent" aria-hidden="true"/>
      </span>

      <div className="flex flex-col gap-1">
        <p className="text-[15px] font-semibold text-ink">Nothing scheduled</p>
        <p className="max-w-[320px] text-[13px] leading-snug text-dim">
          {period_label} is clear. Pick a day in the grid, or start something new here.
        </p>
      </div>

      <button
        type="button"
        onClick={() => on_create()}
        className="mt-1 flex items-center gap-1.5 rounded-[11px] bg-accent px-3.5 py-2 text-[13px] font-bold text-on-accent transition-opacity hover:opacity-90"
      >
        <CalendarPlus className="h-4 w-4"/>
        New event
      </button>
    </div>
  )
}
