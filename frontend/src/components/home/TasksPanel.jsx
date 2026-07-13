import { Check, CheckCircle2, AlertCircle } from 'lucide-react'
import { Panel, PanelHead, EmptyState } from './Panel'

const DUE_COLORS = {
  overdue: 'var(--down)',
  today: 'var(--clay)',
  upcoming: 'var(--faint)',
}

export default function TasksPanel({ tasks, done, total, loading, error, on_toggle })
{
  const progress = total > 0 ? Math.round((done / total) * 100) : 0

  const render_body = () =>
  {
    if (loading)
    {
      return (
        <div className="flex flex-1 flex-col gap-2 pt-1">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-[30px] animate-pulse rounded-[11px] bg-line2"/>
          ))}
        </div>
      )
    }

    if (error)
    {
      return (
        <EmptyState icon={<AlertCircle className="h-[18px] w-[18px]" aria-hidden="true"/>}>
          Couldn&apos;t load your tasks. Try again shortly.
        </EmptyState>
      )
    }

    if (tasks.length === 0)
    {
      return (
        <EmptyState icon={<CheckCircle2 className="h-[18px] w-[18px]" aria-hidden="true"/>}>
          No tasks yet. Add one and it shows up here.
        </EmptyState>
      )
    }

    return (
      <div className="mt-3 flex flex-col gap-2.5">
        {tasks.map((task) => {
          const is_done = task.status === 'done'

          return (
            <div key={task.id} className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() => on_toggle(task)}
                aria-label={is_done ? `Mark "${task.title}" as not done` : `Mark "${task.title}" as done`}
                aria-pressed={is_done}
                className={`flex h-[19px] w-[19px] shrink-0 items-center justify-center rounded-[6px] transition-colors ${
                  is_done ? 'bg-accent' : 'border-[1.8px] border-faint hover:border-accent'
                }`}
              >
                {is_done ? (
                  <Check className="h-[12px] w-[12px] text-on-accent" strokeWidth={3} aria-hidden="true"/>
                ) : null}
              </button>

              <span
                className={`min-w-0 flex-1 truncate text-[13px] ${
                  is_done ? 'text-faint line-through' : ''
                }`}
              >
                {task.title}
              </span>

              {task.due_label ? (
                <span
                  className="num shrink-0 text-[11.5px]"
                  style={{ color: DUE_COLORS[task.bucket] ?? 'var(--faint)' }}
                >
                  {task.due_label}
                </span>
              ) : null}
            </div>
          )
        })}
      </div>
    )
  }

  return (
    <Panel>
      <PanelHead label="Tasks">
        <span className="num text-[11.5px] text-faint">
          {loading || error ? '—' : `${done}/${total}`}
        </span>
      </PanelHead>

      <div className="mt-3 h-[5px] w-full overflow-hidden rounded-full bg-line2">
        <div
          className="h-full rounded-full bg-accent transition-[width] duration-300"
          style={{ width: `${progress}%` }}
        />
      </div>

      {render_body()}
    </Panel>
  )
}
