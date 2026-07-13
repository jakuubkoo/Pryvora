import { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Check, Plug, RefreshCw, TriangleAlert, Unplug } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useAuth } from '@/contexts/AuthContext'

const SYNC_COOLDOWN_SECONDS = 15

const STATUS_LABELS = {
  connected: 'Connected',
  error: 'Needs attention',
  disconnected: 'Disconnected',
}

const STATUS_STYLES = {
  connected: 'border-accent/40 bg-accent/10 text-accent',
  error: 'border-down/40 bg-down/10 text-down',
  disconnected: 'border-line2 bg-panel text-dim',
}

function format_synced_at(value)
{
  if (!value)
  {
    return 'Never synced'
  }

  return `Last synced ${new Date(value).toLocaleString()}`
}

export default function IntegrationsSection()
{
  const { api_request } = useAuth()
  const [search_params, set_search_params] = useSearchParams()

  const [providers, set_providers] = useState([])
  const [loading, set_loading] = useState(true)
  const [error, set_error] = useState('')
  const [success, set_success] = useState('')
  const [busy_key, set_busy_key] = useState('')

  const [connect_provider, set_connect_provider] = useState(null)
  const [connect_values, set_connect_values] = useState({})
  const [connect_error, set_connect_error] = useState('')
  const [connecting, set_connecting] = useState(false)

  const [disconnect_target, set_disconnect_target] = useState(null)

  // Cooldown per provider key, in seconds. Stops the Sync button from queueing
  // a job on every click; the backend throttles it too, this is just the guard
  // the user can see.
  const [cooldowns, set_cooldowns] = useState({})

  const load_providers = async () =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/integration/providers`)

      if (!response.ok)
      {
        throw new Error('Could not load integrations.')
      }

      set_providers(await response.json())
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_loading(false)
    }
  }

  useEffect(() =>
  {
    load_providers()
  }, [])

  // The OAuth callback bounces the browser back here with the outcome in the query
  // string. Show it as the usual banner, then strip the params so a refresh does
  // not replay a stale message.
  useEffect(() =>
  {
    const status = search_params.get('status')

    if (!status)
    {
      return
    }

    if ('connected' === status)
    {
      set_success('Gmail connected. The first sync is running in the background.')
    }
    else
    {
      set_error(search_params.get('message') || 'Could not connect that account.')
    }

    const next = new URLSearchParams(search_params)

    next.delete('integration')
    next.delete('status')
    next.delete('message')

    set_search_params(next, { replace: true })
  }, [search_params, set_search_params])

  const handle_oauth_connect = async (provider) =>
  {
    set_busy_key(provider.key)
    set_error('')
    set_success('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/integration/oauth/${provider.key}/start`,
      )

      const data = await response.json().catch(() => ({}))

      if (!response.ok)
      {
        throw new Error(data.error || 'Could not start the sign-in.')
      }

      // A full navigation, not a fetch: the consent screen is Google's page and the
      // user has to actually see it.
      window.location.assign(data.authorization_url)
    }
    catch (err)
    {
      set_error(err.message)
      set_busy_key('')
    }
  }

  useEffect(() =>
  {
    if (0 === Object.keys(cooldowns).length)
    {
      return
    }

    const timer = window.setInterval(() =>
    {
      set_cooldowns((current) =>
      {
        const next = {}

        Object.entries(current).forEach(([key, seconds]) =>
        {
          if (seconds > 1)
          {
            next[key] = seconds - 1
          }
        })

        return next
      })
    }, 1000)

    return () => window.clearInterval(timer)
  }, [cooldowns])

  const start_cooldown = (key, seconds) =>
  {
    set_cooldowns((current) => ({ ...current, [key]: seconds }))
  }

  const open_connect = (provider) =>
  {
    const values = {}

    provider.form.forEach((field) =>
    {
      values[field.name] = ''
    })

    set_connect_values(values)
    set_connect_error('')
    set_connect_provider(provider)
  }

  const handle_connect = async (event) =>
  {
    event.preventDefault()
    set_connecting(true)
    set_connect_error('')

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/integration/accounts`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ provider: connect_provider.key, credentials: connect_values }),
      })

      const data = await response.json()

      if (!response.ok)
      {
        throw new Error(data.error || 'Could not connect this account.')
      }

      set_connect_provider(null)
      set_success(`${connect_provider.label} connected. The first sync is running in the background.`)
      await load_providers()
    }
    catch (err)
    {
      set_connect_error(err.message)
    }
    finally
    {
      set_connecting(false)
    }
  }

  const handle_sync = async (provider) =>
  {
    if (busy_key === provider.key || cooldowns[provider.key])
    {
      return
    }

    set_busy_key(provider.key)
    set_error('')
    set_success('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/integration/accounts/${provider.account.id}/sync`,
        { method: 'POST' },
      )

      const data = await response.json().catch(() => ({}))

      if (429 === response.status)
      {
        start_cooldown(provider.key, data.retry_after ?? 30)
        set_error(data.error ?? 'Too many sync requests. Try again shortly.')

        return
      }

      if (!response.ok)
      {
        throw new Error('Could not queue a sync.')
      }

      start_cooldown(provider.key, SYNC_COOLDOWN_SECONDS)
      set_success('Sync queued.')
      window.setTimeout(load_providers, 2500)
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_busy_key('')
    }
  }

  const handle_test = async (provider) =>
  {
    if (busy_key === provider.key || cooldowns[provider.key])
    {
      return
    }

    set_busy_key(provider.key)
    set_error('')
    set_success('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/integration/accounts/${provider.account.id}/test`,
        { method: 'POST' },
      )

      const data = await response.json().catch(() => ({}))

      if (429 === response.status)
      {
        start_cooldown(provider.key, data.retry_after ?? 30)
        set_error(data.error ?? 'Too many requests. Try again shortly.')

        return
      }

      if (data.ok)
      {
        set_success('Connection is working.')
      }
      else
      {
        set_error(data.error || 'The connection test failed.')
      }
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_busy_key('')
    }
  }

  const handle_target_change = async (provider, href) =>
  {
    set_busy_key(provider.key)
    set_error('')
    set_success('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/integration/accounts/${provider.account.id}`,
        {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ target_calendar_href: href }),
        },
      )

      if (!response.ok)
      {
        throw new Error('Could not set the target calendar.')
      }

      set_success('New Pryvora events will be added to this calendar.')
      await load_providers()
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_busy_key('')
    }
  }

  const handle_disconnect = async () =>
  {
    const provider = disconnect_target

    set_busy_key(provider.key)
    set_disconnect_target(null)
    set_error('')
    set_success('')

    try
    {
      const response = await api_request(
        `${import.meta.env.VITE_API_URL}/api/integration/accounts/${provider.account.id}`,
        { method: 'DELETE' },
      )

      if (!response.ok)
      {
        throw new Error('Could not disconnect this account.')
      }

      set_success(`${provider.label} disconnected.`)
      await load_providers()
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_busy_key('')
    }
  }

  if (loading)
  {
    return <p className="text-[13px] text-dim">Loading integrations…</p>
  }

  return (
    <div className="space-y-4">
      {error && (
        <p className="flex items-center gap-2 text-[13px] text-down">
          <TriangleAlert className="size-4 shrink-0" aria-hidden="true" />
          {error}
        </p>
      )}
      {success && (
        <p className="flex items-center gap-2 text-[13px] text-accent">
          <Check className="size-4 shrink-0" aria-hidden="true" />
          {success}
        </p>
      )}

      <ul className="space-y-2">
        {providers.map((provider) => (
          <li
            key={provider.key}
            className="flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-line bg-panel-sunk px-3.5 py-3"
          >
            <div className="min-w-0 space-y-1">
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium text-ink">{provider.label}</span>
                {provider.account && (
                  <Badge
                    variant="outline"
                    className={STATUS_STYLES[provider.account.status] ?? STATUS_STYLES.disconnected}
                  >
                    <span className="size-1.5 rounded-full bg-current" aria-hidden="true"/>
                    {STATUS_LABELS[provider.account.status]}
                  </Badge>
                )}
              </div>
              <p className="truncate text-[12px] text-faint">
                {provider.account
                  ? `${provider.account.display_name} · ${format_synced_at(provider.account.last_synced_at)}`
                  : 'Not connected'}
              </p>
              {provider.account?.last_error && (
                <p className="text-[12px] text-down">{provider.account.last_error}</p>
              )}

              {/* Without this a self-hoster who has not set up a Google project just
                  sees a Connect button that fails. */}
              {!provider.account && false === provider.available && (
                <p className="text-[12px] text-faint">{provider.unavailable_reason}</p>
              )}

              {/* Said before consent, not after the first breakage. There is no
                  connect dialog on the OAuth path to put this in. */}
              {'oauth' === provider.auth && !provider.account && false !== provider.available && (
                <p className="text-[12px] text-faint">
                  Read-only: Pryvora can never label, archive or delete your mail.
                  Google expires access every 7 days for personal accounts, so you will
                  need to reconnect weekly.
                </p>
              )}

              {provider.account && provider.account.calendars?.length > 0 && (
                <div className="space-y-1 pt-1">
                  <Select
                    value={provider.account.target_calendar_href ?? ''}
                    disabled={busy_key === provider.key}
                    onValueChange={(href) => handle_target_change(provider, href)}
                  >
                    <SelectTrigger className="h-8 w-[220px] rounded-[10px] border-line bg-panel text-[12px] text-ink">
                      <SelectValue placeholder="Choose a calendar to write to" />
                    </SelectTrigger>
                    <SelectContent>
                      {provider.account.calendars.map((calendar) => (
                        <SelectItem key={calendar.href} value={calendar.href}>
                          {calendar.display_name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {!provider.account.target_calendar_href && (
                    <p className="text-[12px] text-faint">
                      Pick a calendar to push Pryvora events to iCloud.
                    </p>
                  )}
                </div>
              )}
            </div>

            <div className="flex shrink-0 items-center gap-2">
              {provider.account
                ? (
                    <>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={busy_key === provider.key || Boolean(cooldowns[provider.key])}
                        onClick={() => handle_test(provider)}
                      >
                        Test
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={busy_key === provider.key || Boolean(cooldowns[provider.key])}
                        onClick={() => handle_sync(provider)}
                      >
                        <RefreshCw
                          className={`size-3.5 ${busy_key === provider.key ? 'animate-spin' : ''}`}
                          aria-hidden="true"
                        />
                        {cooldowns[provider.key]
                          ? `Wait ${cooldowns[provider.key]}s`
                          : busy_key === provider.key
                            ? 'Syncing…'
                            : 'Sync now'}
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        disabled={busy_key === provider.key}
                        onClick={() => set_disconnect_target(provider)}
                      >
                        <Unplug className="size-3.5" aria-hidden="true" />
                        Disconnect
                      </Button>
                    </>
                  )
                : (
                    <Button
                      size="sm"
                      disabled={false === provider.available || busy_key === provider.key}
                      onClick={() => 'oauth' === provider.auth
                        ? handle_oauth_connect(provider)
                        : open_connect(provider)}
                    >
                      <Plug className="size-3.5" aria-hidden="true" />
                      {'oauth' === provider.auth ? 'Connect with Google' : 'Connect'}
                    </Button>
                  )}
            </div>
          </li>
        ))}
      </ul>

      <Dialog open={Boolean(connect_provider)} onOpenChange={(open) => !open && set_connect_provider(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Connect {connect_provider?.label}</DialogTitle>
            <DialogDescription>
              These credentials are encrypted on your server and never leave it.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_connect} className="space-y-4">
            {connect_error && (
              <p className="flex items-start gap-2 text-[13px] text-down">
                <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {connect_error}
              </p>
            )}

            {connect_provider?.form.map((field) => (
              <div key={field.name} className="space-y-2">
                <Label htmlFor={field.name} className="text-[13px] font-medium text-ink">
                  {field.label}
                </Label>
                <Input
                  id={field.name}
                  type={field.type}
                  required={field.required}
                  autoComplete="off"
                  value={connect_values[field.name] ?? ''}
                  onChange={(e) => set_connect_values({ ...connect_values, [field.name]: e.target.value })}
                  className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink placeholder:text-faint"
                />
                {field.help && <p className="text-[12px] text-faint">{field.help}</p>}
              </div>
            ))}

            <Button type="submit" disabled={connecting} className="w-full">
              {connecting ? 'Connecting…' : 'Connect'}
            </Button>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(disconnect_target)} onOpenChange={(open) => !open && set_disconnect_target(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Disconnect {disconnect_target?.label}?</DialogTitle>
            <DialogDescription>
              The stored credentials and Pryvora's local copy of this account's data will be
              deleted. Nothing changes in the remote account.
            </DialogDescription>
          </DialogHeader>

          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => set_disconnect_target(null)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handle_disconnect}>
              Disconnect
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
