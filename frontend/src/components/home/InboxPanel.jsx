import { inbox_emails } from '@/lib/home_mock_data'
import { Panel, PanelHead } from './Panel'
import { tint } from './tint'

export default function InboxPanel()
{
  const unread_count = inbox_emails.filter((email) => email.unread).length

  return (
    <Panel>
      <PanelHead label="Inbox">
        <span
          className="num rounded-[20px] px-2 py-0.5 text-[11px] font-medium text-accent"
          style={{ background: tint('var(--accent)', 13) }}
        >
          {unread_count} unread
        </span>
      </PanelHead>

      <div className="mt-3 flex flex-col gap-2.5">
        {inbox_emails.map((email) => (
          <div key={email.id} className="flex items-center gap-2.5">
            <span
              className="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full text-[12px] font-semibold"
              style={{ background: tint(email.color, 16), color: email.color }}
              aria-hidden="true"
            >
              {email.from.charAt(0)}
            </span>

            <span className="flex min-w-0 flex-1 flex-col">
              <span className="flex items-center gap-1.5">
                {email.unread ? (
                  <span className="h-[5px] w-[5px] shrink-0 rounded-full bg-accent" aria-hidden="true"/>
                ) : null}
                <span
                  className={`truncate text-[13px] leading-tight ${email.unread ? 'font-semibold' : 'text-dim'}`}
                >
                  {email.from}
                </span>
              </span>
              <span className="truncate text-[11.5px] leading-tight text-dim">{email.subject}</span>
            </span>

            <span className="num shrink-0 text-[11.5px] text-faint">{email.time}</span>
          </div>
        ))}
      </div>
    </Panel>
  )
}
