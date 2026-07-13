import { Wallet, PiggyBank, BarChart3, Coins } from 'lucide-react'
import { cash_accounts } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'
import { tint } from './tint'

const ICONS = {
  wallet: Wallet,
  piggy: PiggyBank,
  chart: BarChart3,
  coins: Coins,
}

export default function CashAccountsPanel()
{
  return (
    <Panel>
      <PanelHead label="Cash accounts">
        <span className="text-[11.5px] text-faint">4 linked</span>
      </PanelHead>

      <div className="mt-3.5 flex flex-col gap-3">
        {cash_accounts.map((account) => {
          const Icon = ICONS[account.icon] ?? Wallet

          return (
            <div key={account.name} className="flex items-center gap-3">
              <span
                className="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[9px]"
                style={{ background: tint(account.color, 14), color: account.color }}
                aria-hidden="true"
              >
                <Icon className="h-[16px] w-[16px]"/>
              </span>

              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-[13px] font-medium leading-tight">{account.name}</span>
                <span className="num truncate text-[11.5px] leading-tight text-faint">{account.mask}</span>
              </span>

              <span className="flex shrink-0 flex-col items-end">
                <span className="num text-[13px] font-medium leading-tight">{account.value}</span>
                <span
                  className={`num text-[11.5px] leading-tight ${account.up ? 'text-up' : 'text-down'}`}
                >
                  {account.change}
                </span>
              </span>
            </div>
          )
        })}
      </div>
    </Panel>
  )
}
