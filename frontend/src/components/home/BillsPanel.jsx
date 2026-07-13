import { bills_due } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'

export default function BillsPanel()
{
  return (
    <Panel>
      <PanelHead label="Bills due">
        <span className="num text-[11.5px] text-faint">{bills_due.length} soon</span>
      </PanelHead>

      <div className="mt-3.5 flex flex-col gap-3.5">
        {bills_due.map((bill) => (
          <div key={bill.name} className="flex items-center gap-2.5">
            <span
              className="h-[8px] w-[8px] shrink-0 rounded-full"
              style={{ background: bill.color }}
              aria-hidden="true"
            />

            <span className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-[13px] font-medium leading-tight">{bill.name}</span>
              <span className="text-[11.5px] leading-tight" style={{ color: bill.color }}>
                {bill.due}
              </span>
            </span>

            <span className="num shrink-0 text-[13px] font-medium">{bill.value}</span>
          </div>
        ))}
      </div>
    </Panel>
  )
}
