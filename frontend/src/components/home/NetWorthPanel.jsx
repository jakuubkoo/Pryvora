import { ArrowUpRight } from 'lucide-react'
import { net_worth, build_spark, account_chips } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'
import { tint } from './tint'

const spark = build_spark(net_worth.series)

export default function NetWorthPanel()
{
  return (
    <Panel>
      <PanelHead label="Net worth">
        <span className="text-[11.5px] text-faint">All accounts</span>
      </PanelHead>

      <div className="mt-3.5 flex flex-wrap items-center gap-x-3 gap-y-2">
        <span className="num text-[44px] font-semibold leading-[1.05] tracking-[-0.025em]">
          {net_worth.total}
        </span>
        <span
          className="num inline-flex items-center gap-1 rounded-[20px] px-2.5 py-1 text-[12.5px] font-medium text-up"
          style={{ background: tint('var(--up)', 12) }}
        >
          <ArrowUpRight className="h-3.5 w-3.5 shrink-0" aria-hidden="true"/>
          {net_worth.change}
        </span>
      </div>

      {/* Full-bleed sparkline — bleeds into the panel's horizontal padding. */}
      <div className="-mx-5 mt-4">
        <svg
          viewBox="0 0 500 120"
          preserveAspectRatio="none"
          className="block h-[120px] w-full"
          role="img"
          aria-label={`Net worth trend, ${net_worth.change}`}
        >
          <defs>
            <linearGradient id="net_worth_fill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="var(--accent)" stopOpacity="0.20"/>
              <stop offset="100%" stopColor="var(--accent)" stopOpacity="0"/>
            </linearGradient>
          </defs>
          <path d={spark.area} fill="url(#net_worth_fill)"/>
          <path
            d={spark.line}
            fill="none"
            stroke="var(--accent)"
            strokeWidth="2.4"
            strokeLinecap="round"
            strokeLinejoin="round"
            vectorEffect="non-scaling-stroke"
          />
        </svg>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {account_chips.map((chip) => (
          <div
            key={chip.name}
            className="min-w-0 flex-1 basis-[calc(50%-4px)] rounded-[11px] border border-line2 px-2.5 py-2 sm:basis-0"
          >
            <div className="flex items-center gap-1.5">
              <span
                className="h-[7px] w-[7px] shrink-0 rounded-[2px]"
                style={{ background: chip.color }}
                aria-hidden="true"
              />
              <span className="truncate text-[11px] font-semibold text-dim">{chip.name}</span>
            </div>
            <div className="num mt-1 text-[15px] font-semibold">{chip.value}</div>
          </div>
        ))}
      </div>
    </Panel>
  )
}
