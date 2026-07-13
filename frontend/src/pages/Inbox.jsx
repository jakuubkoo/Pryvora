import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { motion, AnimatePresence } from 'framer-motion'
import {
  LockIcon,
  InboxIcon,
  Loader2Icon,
  MailIcon,
  MegaphoneIcon,
  NewspaperIcon,
  BellIcon,
  UsersIcon,
  UserRoundIcon,
  WifiOffIcon,
  ExternalLinkIcon,
  EyeOffIcon,
  HelpCircleIcon,
  PlugIcon,
} from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { cn } from '@/lib/utils'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.5,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

const get_container_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: should_reduce ? 0 : 0.04,
      delayChildren: should_reduce ? 0 : 0.08,
    },
  },
})

const get_item_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 10 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.35,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: 0,
    y: should_reduce ? 0 : -8,
    transition: { duration: should_reduce ? 0 : 0.2 },
  },
})

const NOISE_TABS = [
  { key: 'newsletter', label: 'Newsletters', icon: NewspaperIcon },
  { key: 'notification', label: 'Notifications', icon: BellIcon },
  { key: 'promotion', label: 'Promotions', icon: MegaphoneIcon },
  { key: 'social', label: 'Social', icon: UsersIcon },
]

// Turns the classifier's rule ids into something a human can argue with. Kept in
// lockstep with TriageClassifier — if a rule is added there and not here, the Why
// panel falls back to the raw id rather than hiding it.
const RULE_EXPLANATIONS = {
  gmail_spam: 'Gmail marked it as spam',
  user_replied_to_sender: 'You have replied to this sender before',
  promotions_bulk: 'Bulk mail that Gmail filed under Promotions',
  gmail_promotions: 'Gmail filed it under Promotions',
  list_id_header: 'Carries a List-Id header, so it is a mailing list',
  list_unsubscribe_header: 'Carries a List-Unsubscribe header',
  auto_submitted: 'Marked as automatically sent',
  precedence_bulk: 'Marked as bulk mail',
  noreply_sender: 'Sent from a no-reply address',
  gmail_social: 'Gmail filed it under Social',
  gmail_forums: 'Gmail filed it under Forums',
  gmail_updates: 'Gmail filed it under Updates',
  default_priority: 'Nothing marked it as noise',
  starred: 'You starred it',
  gmail_important: 'Gmail thinks it is important',
  addressed_directly: 'Addressed to you directly',
  subject_shouting: 'The subject is shouting',
}

function format_relative(value)
{
  if (!value)
  {
    return ''
  }

  const then = new Date(value)
  const minutes = Math.round((Date.now() - then.getTime()) / 60000)

  if (minutes < 1)
  {
    return 'just now'
  }

  if (minutes < 60)
  {
    return `${minutes}m`
  }

  if (minutes < 1440)
  {
    return `${Math.round(minutes / 60)}h`
  }

  if (minutes < 10080)
  {
    return `${Math.round(minutes / 1440)}d`
  }

  return then.toLocaleDateString()
}

function FilterPill({ is_active, icon, label, count, size, on_click })
{
  const PillIcon = icon

  return (
    <button
      type="button"
      role="tab"
      aria-selected={is_active}
      onClick={on_click}
      className={cn(
        'inline-flex cursor-pointer items-center gap-2 rounded-[20px] transition-colors duration-150',
        size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-3.5 py-2 text-sm',
        is_active
          ? 'bg-[color-mix(in_srgb,var(--accent)_14%,transparent)] font-bold text-ink'
          : 'font-medium text-dim hover:bg-panel-sunk hover:text-ink'
      )}
    >
      <PillIcon className={size === 'sm' ? 'size-3.5' : 'size-4'}/>
      {label}
      {count > 0 && (
        <span
          className={cn(
            'num inline-flex min-w-[1.25rem] justify-center rounded-[20px] px-1.5 py-px text-[11px] font-semibold',
            is_active
              ? 'bg-accent text-on-accent'
              : 'bg-panel-sunk text-faint'
          )}
        >
          {count}
        </span>
      )}
    </button>
  )
}

function WhyPopover({ reason, category })
{
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          className="inline-flex cursor-pointer items-center gap-1 rounded-[8px] px-1.5 py-1 text-[11px] font-medium text-faint transition-colors hover:bg-panel-sunk hover:text-ink"
        >
          <HelpCircleIcon className="size-3.5"/>
          Why?
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[280px] space-y-2 rounded-[12px] border-line bg-panel p-3">
        <p className="text-[12px] font-semibold text-ink">
          Filed as {category}
        </p>
        <ul className="space-y-1.5">
          {reason.map((entry) => (
            <li key={entry.rule} className="flex items-start justify-between gap-2 text-[12px] text-dim">
              <span>{RULE_EXPLANATIONS[entry.rule] ?? entry.rule}</span>
              <span className="num shrink-0 text-faint">
                {entry.weight > 0 ? `+${entry.weight}` : entry.weight}
              </span>
            </li>
          ))}
        </ul>
        <p className="border-t border-line pt-2 text-[11px] text-faint">
          Rules only — no AI read your mail.
        </p>
      </PopoverContent>
    </Popover>
  )
}

function EmailRow({ message, on_dismiss, variants })
{
  const sender = message.from_name || message.from_email

  return (
    <motion.li
      variants={variants}
      exit="exit"
      layout
      className="group flex items-start gap-3 rounded-[var(--radius-row)] border border-line bg-panel-sunk px-3.5 py-3 transition-colors hover:border-line2"
    >
      <span
        className={cn(
          'mt-1.5 size-2 shrink-0 rounded-full',
          message.is_unread ? 'bg-accent' : 'bg-transparent'
        )}
        aria-hidden="true"
      />

      <div className="min-w-0 flex-1 space-y-0.5">
        <div className="flex items-baseline justify-between gap-3">
          <span className="truncate text-[13px] font-medium text-ink">{sender}</span>
          <span className="num shrink-0 text-[11px] text-faint">
            {format_relative(message.received_at)}
          </span>
        </div>

        <p className="truncate text-[13px] text-ink">{message.subject}</p>

        {message.snippet && (
          <p className="truncate text-[12px] text-faint">{message.snippet}</p>
        )}

        <div className="flex flex-wrap items-center gap-1 pt-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
          <a
            href={`https://mail.google.com/mail/u/0/#all/${message.gmail_message_id}`}
            target="_blank"
            rel="noreferrer noopener"
            className="inline-flex items-center gap-1 rounded-[8px] px-1.5 py-1 text-[11px] font-medium text-faint transition-colors hover:bg-panel hover:text-ink"
          >
            <ExternalLinkIcon className="size-3.5"/>
            Open in Gmail
          </a>

          <button
            type="button"
            onClick={() => on_dismiss(message.id)}
            className="inline-flex cursor-pointer items-center gap-1 rounded-[8px] px-1.5 py-1 text-[11px] font-medium text-faint transition-colors hover:bg-panel hover:text-ink"
          >
            <EyeOffIcon className="size-3.5"/>
            Dismiss
          </button>

          {message.category_reason?.length > 0 && (
            <WhyPopover reason={message.category_reason} category={message.category}/>
          )}

          {message.unsubscribe_url && (
            <a
              href={message.unsubscribe_url}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex items-center gap-1 rounded-[8px] px-1.5 py-1 text-[11px] font-medium text-accent transition-colors hover:bg-panel"
            >
              Unsubscribe
            </a>
          )}
        </div>
      </div>
    </motion.li>
  )
}

export default function Inbox()
{
  const { api_request } = useAuth()
  const should_reduce = use_reduced_motion()

  const page_variants = get_page_variants(should_reduce)
  const container_variants = get_container_variants(should_reduce)
  const item_variants = get_item_variants(should_reduce)

  const [messages, set_messages] = useState([])
  const [stats, set_stats] = useState(null)
  const [tab, set_tab] = useState('priority')
  const [loading, set_loading] = useState(true)
  const [error, set_error] = useState('')
  const [connected, set_connected] = useState(true)

  const load = useCallback(async () =>
  {
    set_loading(true)
    set_error('')

    try
    {
      const [list_response, stats_response, providers_response] = await Promise.all([
        api_request(`${import.meta.env.VITE_API_URL}/api/email?category=${tab}`),
        api_request(`${import.meta.env.VITE_API_URL}/api/email/stats`),
        api_request(`${import.meta.env.VITE_API_URL}/api/integration/providers`),
      ])

      if (!list_response.ok || !stats_response.ok)
      {
        throw new Error('Could not load your inbox.')
      }

      const providers = providers_response.ok ? await providers_response.json() : []
      const gmail = providers.find((provider) => 'gmail' === provider.key)

      set_connected(Boolean(gmail?.account))
      set_messages((await list_response.json()).items)
      set_stats(await stats_response.json())
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_loading(false)
    }
  }, [api_request, tab])

  useEffect(() =>
  {
    load()
  }, [load])

  const handle_dismiss = async (id) =>
  {
    // Optimistic: the row is gone from view immediately, and nothing about this
    // reaches Gmail either way.
    set_messages((current) => current.filter((message) => message.id !== id))

    try
    {
      await api_request(`${import.meta.env.VITE_API_URL}/api/email/${id}/dismiss`, { method: 'POST' })
      const stats_response = await api_request(`${import.meta.env.VITE_API_URL}/api/email/stats`)

      if (stats_response.ok)
      {
        set_stats(await stats_response.json())
      }
    }
    catch
    {
      load()
    }
  }

  const noise_count = stats?.noise ?? 0
  const needs_you_count = stats?.needs_you ?? 0

  return (
    <AppLayout>
      <motion.div
        className="space-y-8"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        <header className="space-y-3">
          <div className="space-y-1">
            <h1 className="text-[29px] font-bold leading-tight tracking-[-0.025em] text-ink">
              Inbox
            </h1>
            <p className="text-sm text-dim">
              {stats
                ? `${needs_you_count} need you · ${noise_count} filed as noise.`
                : 'Sorting the mail that matters from the mail that does not.'}
            </p>
          </div>

          {/* The product's whole promise, on the screen where it matters most. Not
              dismissable — it is a fact about the integration, not a notice. */}
          <p className="inline-flex items-center gap-2 rounded-[10px] border border-line bg-panel-sunk px-3 py-2 text-[12px] text-dim">
            <LockIcon className="size-3.5 shrink-0 text-accent" aria-hidden="true"/>
            Read-only. Pryvora cannot label, archive or delete anything in Gmail.
          </p>
        </header>

        {connected && (
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-1" role="tablist">
              <FilterPill
                is_active={'priority' === tab}
                icon={UserRoundIcon}
                label="Needs you"
                count={needs_you_count}
                on_click={() => set_tab('priority')}
              />
              <FilterPill
                is_active={'noise' === tab}
                icon={MailIcon}
                label="Noise"
                count={noise_count}
                on_click={() => set_tab('noise')}
              />
            </div>

            {('priority' !== tab) && (
              <div className="flex flex-wrap items-center gap-1" role="tablist">
                {NOISE_TABS.map((noise_tab) => (
                  <FilterPill
                    key={noise_tab.key}
                    is_active={noise_tab.key === tab}
                    icon={noise_tab.icon}
                    label={noise_tab.label}
                    count={stats?.by_category?.[noise_tab.key] ?? 0}
                    size="sm"
                    on_click={() => set_tab(noise_tab.key)}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        {loading && (
          <div className="flex items-center gap-2 text-sm text-dim">
            <Loader2Icon className="size-4 animate-spin"/>
            Loading your inbox…
          </div>
        )}

        {!loading && error && (
          <div className="flex flex-col items-center gap-3 rounded-[14px] border border-line bg-panel-sunk px-6 py-12 text-center">
            <WifiOffIcon className="size-8 text-faint"/>
            <p className="text-sm text-dim">{error}</p>
            <Button variant="outline" size="sm" onClick={load}>
              Try again
            </Button>
          </div>
        )}

        {!loading && !error && !connected && (
          <div className="flex flex-col items-center gap-3 rounded-[14px] border border-line bg-panel-sunk px-6 py-12 text-center">
            <PlugIcon className="size-8 text-faint"/>
            <div className="space-y-1">
              <p className="text-sm font-medium text-ink">Gmail is not connected</p>
              <p className="text-[13px] text-dim">
                Connect Gmail to see a version of your inbox with the noise filtered out.
              </p>
            </div>
            <Button asChild size="sm">
              <Link to="/settings">Go to Settings</Link>
            </Button>
          </div>
        )}

        {!loading && !error && connected && 0 === messages.length && (
          <div className="flex flex-col items-center gap-3 rounded-[14px] border border-line bg-panel-sunk px-6 py-12 text-center">
            <InboxIcon className="size-8 text-faint"/>
            <p className="text-sm text-dim">
              {'priority' === tab
                ? 'Nothing needs you right now.'
                : 'Nothing here.'}
            </p>
          </div>
        )}

        {!loading && !error && connected && messages.length > 0 && (
          <motion.ul
            className="space-y-2"
            initial="hidden"
            animate="visible"
            variants={container_variants}
          >
            <AnimatePresence mode="popLayout">
              {messages.map((message) => (
                <EmailRow
                  key={message.id}
                  message={message}
                  on_dismiss={handle_dismiss}
                  variants={item_variants}
                />
              ))}
            </AnimatePresence>
          </motion.ul>
        )}

        {!loading && !error && connected && messages.length > 0 && 'priority' !== tab && (
          <p className="text-[12px] text-faint">
            Unsubscribe links open in your browser. Pryvora never clicks them for you.
          </p>
        )}
      </motion.div>
    </AppLayout>
  )
}
