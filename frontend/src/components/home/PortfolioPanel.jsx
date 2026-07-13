import { portfolio } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'
import { tint } from './tint'

const build_conic = (allocation) =>
{
  let cursor = 0
  const stops = allocation.map((slice) =>
  {
    const start = cursor
    cursor += slice.pct
    return `${slice.color} ${start}% ${cursor}%`
  })

  return `conic-gradient(${stops.join(', ')})`
}

export default function PortfolioPanel()
{
  const conic = build_conic(portfolio.allocation)

  return (
    <Panel>
      <PanelHead label="Portfolio">
        <span className="num text-[13px] font-medium">{portfolio.total}</span>
      </PanelHead>

      <div className="mt-4 flex items-center gap-4">
        <div
          className="relative h-[104px] w-[104px] shrink-0 rounded-full"
          style={{ background: conic }}
          role="img"
          aria-label={portfolio.allocation.map((s) => `${s.name} ${s.pct}%`).join(', ')}
        >
          <div className="absolute inset-[15px] flex flex-col items-center justify-center rounded-full bg-panel">
            <span className="eyebrow text-[9px] leading-none">Day</span>
            <span className="num mt-1 text-[14px] font-semibold text-up">{portfolio.day_change}</span>
          </div>
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-2">
          {portfolio.allocation.map((slice) => (
            <div key={slice.name} className="flex items-center gap-2">
              <span
                className="h-[7px] w-[7px] shrink-0 rounded-[2px]"
                style={{ background: slice.color }}
                aria-hidden="true"
              />
              <span className="truncate text-[12.5px] text-dim">{slice.name}</span>
              <span className="num ml-auto shrink-0 text-[12.5px] font-medium">{slice.pct}%</span>
            </div>
          ))}
        </div>
      </div>

      <div className="my-4 h-px bg-line2"/>

      <span className="eyebrow">Top holdings</span>

      <div className="mt-3 flex flex-col gap-2.5">
        {portfolio.holdings.map((holding) => (
          <div key={holding.ticker} className="flex items-center gap-2.5">
            <span
              className="num flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[8px] text-[9.5px] font-semibold"
              style={{ background: tint(holding.color, 14), color: holding.color }}
              aria-hidden="true"
            >
              {holding.ticker}
            </span>

            <span className="min-w-0 flex-1 truncate text-[13px]">{holding.name}</span>

            <span className="flex shrink-0 flex-col items-end">
              <span className="num text-[13px] font-medium leading-tight">{holding.value}</span>
              <span
                className={`num text-[11.5px] leading-tight ${holding.up ? 'text-up' : 'text-down'}`}
              >
                {holding.change}
              </span>
            </span>
          </div>
        ))}
      </div>
    </Panel>
  )
}
