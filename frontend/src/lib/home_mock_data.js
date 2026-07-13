/**
 * MOCK DATA — Pryvora Home.
 *
 * Pryvora's backend covers tasks, calendar events, notes and search. It does NOT
 * cover finance, email or an assistant, so the panels below are driven from here
 * rather than from the API. Every export is a TODO with the endpoint it is waiting on.
 *
 * TODO(net_worth_series, account_chips, cash_accounts) -> GET /api/account
 * TODO(portfolio_allocation, top_holdings)             -> GET /api/portfolio
 * TODO(monthly_spending)                               -> GET /api/spending?month=
 * TODO(bills_due)                                      -> GET /api/bill?status=due
 * TODO(inbox_emails)                                   -> GET /api/mail?folder=inbox
 * TODO(vora_insights, vora_reply)                      -> POST /api/assistant
 *
 * Nothing here reaches the network. vora_reply is a local rule-based responder, not a model.
 */

/**
 * Build a smooth sparkline path from a series of values.
 * Returns { line, area } as SVG path data in a 500x120 viewBox.
 */
export function build_spark(values, width = 500, height = 120, pad = 10)
{
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = (max - min) || 1
  const step = width / (values.length - 1)

  const points = values.map((v, i) => [i * step, height - pad - ((v - min) / range) * (height - pad * 2)])

  let line = ''
  for (let i = 0; i < points.length; i++)
  {
    if (i === 0)
    {
      line += `M ${points[i][0].toFixed(1)} ${points[i][1].toFixed(1)}`
      continue
    }

    const p0 = points[i - 1]
    const p1 = points[i]
    const cx = (p0[0] + p1[0]) / 2
    line += ` C ${cx.toFixed(1)} ${p0[1].toFixed(1)}, ${cx.toFixed(1)} ${p1[1].toFixed(1)}, ${p1[0].toFixed(1)} ${p1[1].toFixed(1)}`
  }

  return { line, area: `${line} L ${width} ${height} L 0 ${height} Z` }
}

export const net_worth = {
  total: '$248,930',
  change: '+$3,120 · +1.3%',
  series: [228, 231, 229, 234, 233, 238, 236, 241, 240, 245, 246, 248.9],
}

export const account_chips = [
  { name: 'Checking',  value: '$12,430',  color: 'var(--accent)' },
  { name: 'Savings',   value: '$48,900',  color: 'var(--gold)' },
  { name: 'Brokerage', value: '$164,200', color: 'var(--clay)' },
  { name: 'Crypto',    value: '$23,400',  color: 'var(--sage)' },
]

export const portfolio = {
  total: '$211,000',
  day_change: '+1.3%',
  allocation: [
    { name: 'Stocks', pct: 52, color: 'var(--accent)' },
    { name: 'Bonds',  pct: 18, color: 'var(--gold)' },
    { name: 'Crypto', pct: 14, color: 'var(--clay)' },
    { name: 'Cash',   pct: 16, color: 'var(--sage)' },
  ],
  holdings: [
    { ticker: 'VOO',  name: 'Vanguard S&P 500', value: '$62,400', change: '+0.9%', up: true,  color: 'var(--accent)' },
    { ticker: 'NVDA', name: 'NVIDIA',           value: '$28,100', change: '+3.1%', up: true,  color: 'var(--clay)' },
    { ticker: 'BTC',  name: 'Bitcoin',          value: '$19,800', change: '-1.2%', up: false, color: 'var(--gold)' },
  ],
}

export const cash_accounts = [
  { name: 'Checking',      mask: '•• 4021',      value: '$12,430',  change: '-$310',   up: false, color: 'var(--accent)', icon: 'wallet' },
  { name: 'Savings',       mask: '•• 8830',      value: '$48,900',  change: '+$120',   up: true,  color: 'var(--gold)',   icon: 'piggy' },
  { name: 'Brokerage',     mask: '•• 5567',      value: '$164,200', change: '+$3,120', up: true,  color: 'var(--clay)',   icon: 'chart' },
  { name: 'Crypto wallet', mask: 'self-custody', value: '$23,400',  change: '-$280',   up: false, color: 'var(--sage)',   icon: 'coins' },
]

export const monthly_spending = [
  { name: 'Housing',       value: 1400, color: 'var(--accent)' },
  { name: 'Food',          value: 680,  color: 'var(--gold)' },
  { name: 'Transport',     value: 420,  color: 'var(--clay)' },
  { name: 'Fun',           value: 380,  color: 'var(--violet)' },
  { name: 'Subscriptions', value: 300,  color: 'var(--sage)' },
]

export const bills_due = [
  { name: 'Rent',        due: 'Due Fri',  value: '$2,400', color: 'var(--down)' },
  { name: 'Credit card', due: 'Due Mon',  value: '$860',   color: 'var(--clay)' },
  { name: 'Utilities',   due: 'Due 28th', value: '$140',   color: 'var(--sage)' },
]

export const inbox_emails = [
  { id: 'e1', from: 'Sam Rivera', subject: 'Re: Q3 planning draft — quick note', time: '09:12', unread: true,  color: 'var(--accent)' },
  { id: 'e2', from: 'Chase Bank', subject: 'Your February statement is ready',    time: '08:40', unread: true,  color: 'var(--clay)' },
  { id: 'e3', from: 'Figma',      subject: '3 comments on “Home v4”',             time: 'Yest',  unread: true,  color: 'var(--violet)' },
  { id: 'e4', from: 'Maya Lin',   subject: 'Lunch Thursday?',                     time: 'Yest',  unread: false, color: 'var(--gold)' },
]

export const vora_insights = [
  { tag: 'Money',    color: 'var(--sage)', text: 'Portfolio is up 1.3% today, led by NVDA. Stocks are slightly above your 50% target.' },
  { tag: 'Heads up', color: 'var(--gold)', text: 'Rent of $2,400 is due Friday. Your checking covers it.' },
]

export const vora_suggestions = [
  "How's my portfolio?",
  'Summarize my inbox',
]

/**
 * Local rule-based responder. There is no assistant backend — this never calls out.
 * The fallback copy says so explicitly rather than implying a live model.
 */
export function vora_reply(text)
{
  const t = text.toLowerCase()

  if (/(portfolio|stock|invest|market)/.test(t))
  {
    return 'Portfolio is up 1.3% today, led by NVDA (+3.1%). Stocks are 52% of your allocation — slightly above your 50% target. Want me to draft a rebalance?'
  }

  if (/(spend|budget|money|balance|cash)/.test(t))
  {
    return "You've spent $3,180 this month, mostly housing. Cash across accounts is $61,330. Rent ($2,400) is due Friday — you're covered."
  }

  if (/(meeting|calendar|today|schedule)/.test(t))
  {
    return 'Three meetings today. Next is the Design review at 14:00. Mornings are clear — I can hold 09:00–11:00 for focus.'
  }

  if (/(mail|email|inbox)/.test(t))
  {
    return '5 unread. Two look time-sensitive: Sam about the Q3 draft, and your bank re: a statement. Want me to summarize them?'
  }

  if (/\b(hi|hello|hey)\b/.test(t))
  {
    return 'Hi! Everything here runs on your own server. What do you want to look at — money, mail, or your day?'
  }

  return "On it — and it all stays local. (Design preview: there's no assistant backend yet, so I'm improvising.)"
}
