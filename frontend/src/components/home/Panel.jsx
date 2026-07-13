import { cn } from '@/lib/utils'

/**
 * Shared shell for every Home panel: .panel surface, 18px/20px padding.
 * Borders only — never box-shadow.
 */
export function Panel({ className, children, ...rest })
{
  return (
    <div
      className={cn('panel flex h-full min-w-0 flex-col px-5 py-[18px]', className)}
      {...rest}
    >
      {children}
    </div>
  )
}

/** Eyebrow on the left, an optional meta node on the right. */
export function PanelHead({ label, children })
{
  return (
    <div className="flex min-h-[18px] items-center justify-between gap-3">
      <span className="eyebrow">{label}</span>
      {children}
    </div>
  )
}

/**
 * First-run / empty state. A calm lucide icon and one line of copy —
 * never a blank card.
 */
export function EmptyState({ icon, children })
{
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2.5 px-2 py-8 text-center text-faint">
      {icon}
      <p className="text-[13px] leading-snug text-dim">{children}</p>
    </div>
  )
}
