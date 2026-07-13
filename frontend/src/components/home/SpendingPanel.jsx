import { monthly_spending } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'

const format_usd = (value) => `$${value.toLocaleString('en-US')}`

export default function SpendingPanel()
{
  const total = monthly_spending.reduce((sum, category) => sum + category.value, 0)
  const max = Math.max(...monthly_spending.map((category) => category.value))

  return (
    <Panel>
      <PanelHead label="Spending · this month">
        <span className="num text-[13px] font-medium">{format_usd(total)}</span>
      </PanelHead>

      <div className="mt-4 flex flex-col gap-3">
        {monthly_spending.map((category) => (
          <div key={category.name} className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between gap-2">
              <span className="truncate text-[12.5px] text-dim">{category.name}</span>
              <span className="num shrink-0 text-[12.5px] font-medium">{format_usd(category.value)}</span>
            </div>
            <div className="h-[7px] w-full overflow-hidden rounded-full bg-line2">
              <div
                className="h-full rounded-full"
                style={{
                  width: `${(category.value / max) * 100}%`,
                  background: category.color,
                }}
              />
            </div>
          </div>
        ))}
      </div>
    </Panel>
  )
}
