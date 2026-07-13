import { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import {
  Check,
  Copy,
  Download,
  KeyRound,
  Lock,
  Moon,
  Pencil,
  QrCode,
  Server,
  Shield,
  ShieldCheck,
  Sun,
  Tags,
  Trash2,
  TriangleAlert,
} from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import IntegrationsSection from '@/components/settings/IntegrationsSection'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/components/theme-provider'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'

const TONES = {
  accent: {
    background: 'color-mix(in srgb, var(--accent) 10%, transparent)',
    borderColor: 'color-mix(in srgb, var(--accent) 26%, transparent)',
    color: 'var(--accent)',
  },
  down: {
    background: 'color-mix(in srgb, var(--down) 10%, transparent)',
    borderColor: 'color-mix(in srgb, var(--down) 26%, transparent)',
    color: 'var(--down)',
  },
  gold: {
    background: 'color-mix(in srgb, var(--gold) 13%, transparent)',
    borderColor: 'color-mix(in srgb, var(--gold) 30%, transparent)',
    color: 'var(--gold)',
  },
}

function StatusNote({ tone = 'accent', icon: Icon, children })
{
  const style = TONES[tone]

  return (
    <div
      role="status"
      className="flex items-start gap-2.5 rounded-[11px] border px-3.5 py-3 text-sm"
      style={style}
    >
      {Icon && <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true"/>}
      <span className="min-w-0 leading-relaxed">{children}</span>
    </div>
  )
}

function PostureFact(props)
{
  const Icon = props.icon

  return (
    <li className="flex items-start gap-2.5">
      <Icon className="mt-0.5 size-3.5 shrink-0 text-faint" aria-hidden="true"/>
      <span className="text-[13px] leading-relaxed text-dim">{props.children}</span>
    </li>
  )
}

function SectionCard({ eyebrow, title, description, children, delay = 0, reduce_motion })
{
  const animation = reduce_motion
    ? {}
    : {
        initial: { opacity: 0, y: 8 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.32, delay, ease: [0.22, 1, 0.36, 1] },
      }

  return (
    <motion.div {...animation}>
      <Card className="gap-5 rounded-[18px] border-line bg-panel shadow-none">
        <CardHeader className="gap-1.5">
          <span className="eyebrow">{eyebrow}</span>
          <CardTitle className="text-[17px] font-semibold tracking-[-0.01em] text-ink">
            {title}
          </CardTitle>
          <CardDescription className="text-[13px] text-dim">
            {description}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {children}
        </CardContent>
      </Card>
    </motion.div>
  )
}

const THEME_OPTIONS = [
  { value: 'light', label: 'Light', icon: Sun },
  { value: 'dark', label: 'Dark', icon: Moon },
]

export default function Settings()
{
  const { user, api_request } = useAuth()
  const { theme, setTheme } = useTheme()
  const reduce_motion = use_reduced_motion()

  const [two_factor_enabled, set_two_factor_enabled] = useState(false)
  const [loading, set_loading] = useState(false)
  const [error, set_error] = useState('')
  const [success, set_success] = useState('')
  const [qr_code, set_qr_code] = useState('')
  const [secret, set_secret] = useState('')
  const [verification_code, set_verification_code] = useState('')
  const [disable_code, set_disable_code] = useState('')
  const [show_setup_modal, set_show_setup_modal] = useState(false)
  const [show_disable_modal, set_show_disable_modal] = useState(false)
  const [setup_step, set_setup_step] = useState(1)
  const [recovery_codes, set_recovery_codes] = useState([])
  const [show_recovery_codes_modal, set_show_recovery_codes_modal] = useState(false)
  const [recovery_copied, set_recovery_copied] = useState(false)

  // Password change state
  const [current_password, set_current_password] = useState('')
  const [new_password, set_new_password] = useState('')
  const [confirm_password, set_confirm_password] = useState('')
  const [password_loading, set_password_loading] = useState(false)
  const [password_error, set_password_error] = useState('')
  const [password_success, set_password_success] = useState('')

  // Tags management state
  const [tags, set_tags] = useState([])
  const [tags_loading, set_tags_loading] = useState(false)
  const [show_edit_tag_modal, set_show_edit_tag_modal] = useState(false)
  const [show_delete_tag_modal, set_show_delete_tag_modal] = useState(false)
  const [current_tag, set_current_tag] = useState(null)
  const [edit_tag_name, set_edit_tag_name] = useState('')
  const [edit_tag_color, set_edit_tag_color] = useState('#3b82f6')
  const [tag_error, set_tag_error] = useState('')
  const [tag_success, set_tag_success] = useState('')

  useEffect(() =>
  {
    if (user)
    {
      set_two_factor_enabled(user.two_factor_enabled)
    }
    fetch_tags()
  }, [user])

  const fetch_tags = async () =>
  {
    set_tags_loading(true)
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/tag/`)

      if (response.ok)
      {
        const data = await response.json()
        set_tags(data)
      }
    }
    catch (err)
    {
      console.error('Failed to fetch tags:', err)
    }
    finally
    {
      set_tags_loading(false)
    }
  }

  const handle_setup_2fa = async () =>
  {
    set_error('')
    set_success('')
    set_loading(true)
    set_setup_step(1)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/2fa/setup`, {
        method: 'POST',
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to setup 2FA')
      }

      const data = await response.json()
      set_qr_code(data.qr_code)
      set_secret(data.secret)
      set_show_setup_modal(true)
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

  const handle_enable_2fa = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_success('')
    set_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/2fa/enable`, {
        method: 'POST',
        body: JSON.stringify({ code: verification_code }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to enable 2FA')
      }

      const data = await response.json()

      set_success('2FA enabled successfully! Your account is now more secure.')
      set_two_factor_enabled(true)
      set_show_setup_modal(false)
      set_qr_code('')
      set_secret('')
      set_verification_code('')
      set_setup_step(1)

      // Show recovery codes
      if (data.recovery_codes && data.recovery_codes.length > 0)
      {
        set_recovery_codes(data.recovery_codes)
        set_recovery_copied(false)
        set_show_recovery_codes_modal(true)
      }
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

  const handle_disable_2fa = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_success('')
    set_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/2fa/disable`, {
        method: 'POST',
        body: JSON.stringify({ code: disable_code }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to disable 2FA')
      }

      set_success('2FA disabled successfully')
      set_two_factor_enabled(false)
      set_show_disable_modal(false)
      set_disable_code('')
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

  const close_setup_modal = () =>
  {
    set_show_setup_modal(false)
    set_qr_code('')
    set_secret('')
    set_verification_code('')
    set_setup_step(1)
    set_error('')
  }

  const close_disable_modal = () =>
  {
    set_show_disable_modal(false)
    set_disable_code('')
    set_error('')
  }

  const close_recovery_codes_modal = () =>
  {
    set_show_recovery_codes_modal(false)
    set_recovery_codes([])
  }

  const download_recovery_codes = () =>
  {
    const text = recovery_codes.join('\n')
    const blob = new Blob([text], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'pryvora-recovery-codes.txt'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  const copy_recovery_codes = () =>
  {
    const text = recovery_codes.join('\n')
    navigator.clipboard.writeText(text)
    set_recovery_copied(true)
    set_success('Recovery codes copied to clipboard!')
    setTimeout(() => set_success(''), 3000)
  }

  const handle_change_password = async (e) =>
  {
    e.preventDefault()
    set_password_error('')
    set_password_success('')

    if (new_password !== confirm_password)
    {
      set_password_error('New passwords do not match')
      return
    }

    if (new_password.length < 6)
    {
      set_password_error('Password must be at least 6 characters')
      return
    }

    set_password_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/user/change-password`, {
        method: 'POST',
        body: JSON.stringify({
          current_password: current_password,
          new_password: new_password,
        }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to change password')
      }

      set_password_success('Password changed successfully!')
      set_current_password('')
      set_new_password('')
      set_confirm_password('')
    }
    catch (err)
    {
      set_password_error(err.message)
    }
    finally
    {
      set_password_loading(false)
    }
  }

  const handle_edit_tag = async (e) =>
  {
    e.preventDefault()
    set_tag_error('')
    set_tag_success('')
    set_tags_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/tag/${current_tag.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          name: edit_tag_name,
          color: edit_tag_color,
        }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to update tag')
      }

      set_tag_success('Tag updated successfully!')
      set_show_edit_tag_modal(false)
      set_current_tag(null)
      await fetch_tags()
      setTimeout(() => set_tag_success(''), 3000)
    }
    catch (err)
    {
      set_tag_error(err.message)
    }
    finally
    {
      set_tags_loading(false)
    }
  }

  const handle_delete_tag = async () =>
  {
    set_tag_error('')
    set_tag_success('')
    set_tags_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/tag/${current_tag.id}`, {
        method: 'DELETE',
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to delete tag')
      }

      set_tag_success('Tag deleted successfully!')
      set_show_delete_tag_modal(false)
      set_current_tag(null)
      await fetch_tags()
      setTimeout(() => set_tag_success(''), 3000)
    }
    catch (err)
    {
      set_tag_error(err.message)
    }
    finally
    {
      set_tags_loading(false)
    }
  }

  const open_edit_tag_modal = (tag) =>
  {
    set_current_tag(tag)
    set_edit_tag_name(tag.name)
    set_edit_tag_color(tag.color)
    set_show_edit_tag_modal(true)
  }

  const open_delete_tag_modal = (tag) =>
  {
    set_current_tag(tag)
    set_show_delete_tag_modal(true)
  }

  const step_labels = ['Scan', 'Secret', 'Verify']

  return (
    <AppLayout>
      <div className="space-y-8 pb-10">
        <header className="space-y-1.5">
          <h1 className="text-[29px] font-bold leading-tight tracking-[-0.025em] text-ink">
            Settings
          </h1>
          <p className="text-sm text-dim">
            Your account, your keys, your archive. Everything here stays on your server.
          </p>
        </header>

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] lg:items-start">
          <div className="space-y-6">
            {/* Two-Factor Authentication */}
            <SectionCard
              eyebrow="Security"
              title="Two-Factor Authentication"
              description="Add a second key to your account with a time-based one-time password."
              delay={0}
              reduce_motion={reduce_motion}
            >
              <div className="space-y-4">
                {error && (
                  <StatusNote tone="down" icon={TriangleAlert}>{error}</StatusNote>
                )}
                {success && (
                  <StatusNote tone="accent" icon={Check}>{success}</StatusNote>
                )}

                {!two_factor_enabled && (
                  <div className="space-y-4">
                    <div className="flex items-start gap-3 rounded-[11px] border border-line2 bg-panel-sunk px-3.5 py-3">
                      <Shield className="mt-0.5 size-4 shrink-0 text-faint" aria-hidden="true"/>
                      <p className="text-[13px] leading-relaxed text-dim">
                        Two-factor authentication is off. Turn it on and a code from your
                        authenticator app will be required alongside your password.
                      </p>
                    </div>
                    <Button onClick={handle_setup_2fa} disabled={loading}>
                      {loading ? 'Setting up...' : 'Enable 2FA'}
                    </Button>
                  </div>
                )}

                {two_factor_enabled && (
                  <div className="space-y-4">
                    <div
                      className="flex items-start gap-3 rounded-[11px] border px-3.5 py-3"
                      style={TONES.accent}
                    >
                      <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden="true"/>
                      <div className="min-w-0">
                        <p className="text-sm font-medium">Two-factor authentication is on</p>
                        <p className="mt-0.5 text-[13px] text-dim">
                          Sign-in requires a TOTP code from your authenticator app.
                        </p>
                      </div>
                    </div>
                    <Button
                      onClick={() => set_show_disable_modal(true)}
                      variant="outline"
                      className="border-line text-down hover:bg-panel-sunk hover:text-down"
                    >
                      Disable 2FA
                    </Button>
                  </div>
                )}

                <Separator className="bg-line2"/>

                <div className="space-y-2.5">
                  <p className="eyebrow">How your data is held</p>
                  <ul className="space-y-2">
                    <PostureFact icon={Lock}>
                      Entries are encrypted at rest with AES-256-GCM.
                    </PostureFact>
                    <PostureFact icon={KeyRound}>
                      Passwords are hashed with Argon2id, never stored in the clear.
                    </PostureFact>
                    <PostureFact icon={Server}>
                      Pryvora is self-hosted — the data never leaves your server.
                    </PostureFact>
                  </ul>
                </div>
              </div>
            </SectionCard>

            {/* Change Password */}
            <SectionCard
              eyebrow="Credentials"
              title="Change Password"
              description="Update the password used to unlock your account."
              delay={0.06}
              reduce_motion={reduce_motion}
            >
              <form onSubmit={handle_change_password} className="space-y-4">
                {password_error && (
                  <StatusNote tone="down" icon={TriangleAlert}>{password_error}</StatusNote>
                )}
                {password_success && (
                  <StatusNote tone="accent" icon={Check}>{password_success}</StatusNote>
                )}

                <div className="space-y-2">
                  <Label htmlFor="current_password" className="text-[13px] font-medium text-ink">
                    Current password
                  </Label>
                  <Input
                    id="current_password"
                    type="password"
                    value={current_password}
                    onChange={(e) => set_current_password(e.target.value)}
                    required
                    autoComplete="current-password"
                    className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink placeholder:text-faint"
                    placeholder="Enter your current password"
                  />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="new_password" className="text-[13px] font-medium text-ink">
                      New password
                    </Label>
                    <Input
                      id="new_password"
                      type="password"
                      value={new_password}
                      onChange={(e) => set_new_password(e.target.value)}
                      required
                      autoComplete="new-password"
                      className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink placeholder:text-faint"
                      placeholder="At least 6 characters"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="confirm_password" className="text-[13px] font-medium text-ink">
                      Confirm new password
                    </Label>
                    <Input
                      id="confirm_password"
                      type="password"
                      value={confirm_password}
                      onChange={(e) => set_confirm_password(e.target.value)}
                      required
                      autoComplete="new-password"
                      className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink placeholder:text-faint"
                      placeholder="Repeat the new password"
                    />
                  </div>
                </div>

                <Button type="submit" disabled={password_loading}>
                  {password_loading ? 'Changing password...' : 'Change password'}
                </Button>
              </form>
            </SectionCard>
          </div>

          <div className="space-y-6">
            {/* Appearance */}
            <SectionCard
              eyebrow="Appearance"
              title="Theme"
              description="Warm paper by day, low light by night."
              delay={0.12}
              reduce_motion={reduce_motion}
            >
              <div
                role="group"
                aria-label="Theme"
                className="flex gap-1 rounded-[11px] border border-line2 bg-panel-sunk p-1"
              >
                {THEME_OPTIONS.map((option) =>
                {
                  const is_active = theme === option.value
                  const Icon = option.icon

                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => setTheme(option.value)}
                      aria-pressed={is_active}
                      className={`relative flex flex-1 items-center justify-center gap-2 rounded-[8px] px-3 py-2 text-[13px] font-medium transition-colors ${
                        is_active ? 'text-ink' : 'text-dim hover:text-ink'
                      }`}
                    >
                      {is_active && (
                        <motion.span
                          layoutId={reduce_motion ? undefined : 'theme_indicator'}
                          transition={{ duration: 0.22, ease: [0.22, 1, 0.36, 1] }}
                          className="absolute inset-0 rounded-[8px] border border-line bg-panel"
                          aria-hidden="true"
                        />
                      )}
                      <span className="relative flex items-center gap-2">
                        <Icon className="size-4" aria-hidden="true"/>
                        {option.label}
                      </span>
                    </button>
                  )
                })}
              </div>
            </SectionCard>

            {/* Manage Tags */}
            <SectionCard
              eyebrow="Library"
              title="Manage Tags"
              description="Rename, recolour, or retire the tags across your archive."
              delay={0.18}
              reduce_motion={reduce_motion}
            >
              <div className="space-y-4">
                {tag_success && (
                  <StatusNote tone="accent" icon={Check}>{tag_success}</StatusNote>
                )}
                {tag_error && (
                  <StatusNote tone="down" icon={TriangleAlert}>{tag_error}</StatusNote>
                )}

                {tags_loading && tags.length === 0 ? (
                  <div className="space-y-2" aria-busy="true" aria-live="polite">
                    {[0, 1, 2].map((row) => (
                      <div
                        key={row}
                        className="h-[52px] rounded-[11px] border border-line2 bg-panel-sunk"
                        style={reduce_motion ? undefined : { animation: 'pulse 1.6s ease-in-out infinite', animationDelay: `${row * 0.12}s` }}
                      />
                    ))}
                    <span className="sr-only">Loading tags</span>
                  </div>
                ) : tags.length === 0 ? (
                  <div className="flex flex-col items-center gap-2 rounded-[11px] border border-dashed border-line bg-panel-sunk px-6 py-10 text-center">
                    <Tags className="size-6 text-faint" aria-hidden="true"/>
                    <p className="text-sm font-medium text-ink">No tags yet</p>
                    <p className="max-w-[38ch] text-[13px] leading-relaxed text-dim">
                      Tags appear here once you add them to a note. They are the shelves
                      of your archive — create the first one while writing.
                    </p>
                  </div>
                ) : (
                  <ul className="space-y-2">
                    {tags.map((tag) => (
                      <li
                        key={tag.id}
                        className="flex items-center justify-between gap-3 rounded-[11px] border border-line2 bg-panel-sunk px-3 py-2.5"
                      >
                        <div className="flex min-w-0 items-center gap-3">
                          <span
                            className="size-3.5 shrink-0 rounded-full border border-line"
                            style={{ backgroundColor: tag.color }}
                            aria-hidden="true"
                          />
                          <span className="truncate text-sm text-ink">{tag.name}</span>
                        </div>
                        <div className="flex shrink-0 gap-1">
                          <Button
                            onClick={() => open_edit_tag_modal(tag)}
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Edit tag ${tag.name}`}
                            className="text-dim hover:bg-panel hover:text-ink"
                          >
                            <Pencil className="size-4"/>
                          </Button>
                          <Button
                            onClick={() => open_delete_tag_modal(tag)}
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Delete tag ${tag.name}`}
                            className="text-dim hover:bg-panel hover:text-down"
                          >
                            <Trash2 className="size-4"/>
                          </Button>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </SectionCard>

            {/* Integrations */}
            <SectionCard
              eyebrow="Integrations"
              title="Connected accounts"
              description="Bring your calendar in. Credentials are encrypted on your server and never leave it."
              delay={0.24}
              reduce_motion={reduce_motion}
            >
              <IntegrationsSection />
            </SectionCard>
          </div>
        </div>
      </div>

      {/* Setup 2FA Modal */}
      <Dialog open={show_setup_modal} onOpenChange={close_setup_modal}>
        <DialogContent className="rounded-[18px] border-line bg-panel sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="text-[19px] font-semibold tracking-[-0.015em] text-ink">
              Enable Two-Factor Authentication
            </DialogTitle>
            <DialogDescription className="text-[13px] text-dim">
              Three steps: scan, save the secret, verify.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6 py-2">
            {/* Progress Indicator */}
            <ol className="flex items-center justify-center gap-2" aria-label="Setup progress">
              {[1, 2, 3].map((step, index) => (
                <li key={step} className="flex items-center gap-2">
                  {index > 0 && (
                    <span
                      className="h-px w-10 transition-colors"
                      style={{ backgroundColor: setup_step >= step ? 'var(--accent)' : 'var(--line)' }}
                      aria-hidden="true"
                    />
                  )}
                  <span
                    className="num flex size-8 items-center justify-center rounded-full border text-[13px] font-semibold transition-colors"
                    style={setup_step >= step
                      ? { background: 'var(--accent)', borderColor: 'var(--accent)', color: 'var(--on-accent)' }
                      : { background: 'var(--panel-sunk)', borderColor: 'var(--line)', color: 'var(--faint)' }}
                    aria-current={setup_step === step ? 'step' : undefined}
                  >
                    {step}
                    <span className="sr-only"> {step_labels[index]}</span>
                  </span>
                </li>
              ))}
            </ol>

            {error && (
              <StatusNote tone="down" icon={TriangleAlert}>{error}</StatusNote>
            )}

            {/* Step 1: Scan QR Code */}
            {setup_step === 1 && (
              <div className="space-y-4">
                <div className="space-y-1 text-center">
                  <h3 className="flex items-center justify-center gap-2 text-sm font-semibold text-ink">
                    <QrCode className="size-4 text-faint" aria-hidden="true"/>
                    Scan the QR code
                  </h3>
                  <p className="text-[13px] text-dim">
                    Use Google Authenticator, Authy, or any TOTP app.
                  </p>
                </div>
                <div className="flex justify-center">
                  <div className="rounded-[11px] border border-line bg-white p-4">
                    <img src={qr_code} alt="Two-factor authentication QR code" className="size-48"/>
                  </div>
                </div>
                <Button onClick={() => set_setup_step(2)} className="w-full" autoFocus>
                  I've scanned the code
                </Button>
              </div>
            )}

            {/* Step 2: Manual Entry */}
            {setup_step === 2 && (
              <div className="space-y-4">
                <div className="space-y-1 text-center">
                  <h3 className="text-sm font-semibold text-ink">Set-up key (optional)</h3>
                  <p className="text-[13px] text-dim">
                    Can't scan? Enter this secret manually in your app.
                  </p>
                </div>
                <div className="space-y-2">
                  <code className="num block break-all rounded-[11px] border border-line bg-panel-sunk p-4 text-center text-sm tracking-[0.08em] text-ink">
                    {secret}
                  </code>
                  <p className="text-center text-xs text-faint">
                    Keep this secret safe and never share it.
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button
                    onClick={() => set_setup_step(1)}
                    variant="outline"
                    className="flex-1 border-line text-dim hover:bg-panel-sunk hover:text-ink"
                  >
                    Back
                  </Button>
                  <Button onClick={() => set_setup_step(3)} className="flex-1">
                    Continue
                  </Button>
                </div>
              </div>
            )}

            {/* Step 3: Verify */}
            {setup_step === 3 && (
              <form onSubmit={handle_enable_2fa} className="space-y-4">
                <div className="space-y-1 text-center">
                  <h3 className="text-sm font-semibold text-ink">Verify the code</h3>
                  <p className="text-[13px] text-dim">
                    Enter the 6-digit code from your authenticator app.
                  </p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="verification_code" className="sr-only">
                    Six-digit verification code
                  </Label>
                  <Input
                    id="verification_code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder="000000"
                    value={verification_code}
                    onChange={(e) => set_verification_code(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    required
                    maxLength={6}
                    className="num h-14 rounded-[11px] border-line bg-panel-sunk text-center text-2xl tracking-[0.35em] text-ink placeholder:text-faint md:text-2xl"
                    autoFocus
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    onClick={() => set_setup_step(2)}
                    variant="outline"
                    className="flex-1 border-line text-dim hover:bg-panel-sunk hover:text-ink"
                  >
                    Back
                  </Button>
                  <Button
                    type="submit"
                    disabled={loading || verification_code.length !== 6}
                    className="flex-1"
                  >
                    {loading ? 'Verifying...' : 'Enable 2FA'}
                  </Button>
                </div>
              </form>
            )}
          </div>
        </DialogContent>
      </Dialog>

      {/* Disable 2FA Modal */}
      <Dialog open={show_disable_modal} onOpenChange={close_disable_modal}>
        <DialogContent className="rounded-[18px] border-line bg-panel sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="text-[19px] font-semibold tracking-[-0.015em] text-ink">
              Disable Two-Factor Authentication
            </DialogTitle>
            <DialogDescription className="text-[13px] text-dim">
              Confirm with a current code to remove the second factor.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_disable_2fa} className="space-y-4">
            {error && (
              <StatusNote tone="down" icon={TriangleAlert}>{error}</StatusNote>
            )}

            <StatusNote tone="gold" icon={TriangleAlert}>
              Your account will be protected by password alone.
            </StatusNote>

            <div className="space-y-2">
              <Label htmlFor="disable_code" className="text-[13px] font-medium text-ink">
                Verification code
              </Label>
              <Input
                id="disable_code"
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="000000"
                value={disable_code}
                onChange={(e) => set_disable_code(e.target.value.replace(/\D/g, '').slice(0, 6))}
                required
                maxLength={6}
                className="num h-14 rounded-[11px] border-line bg-panel-sunk text-center text-xl tracking-[0.35em] text-ink placeholder:text-faint"
                autoFocus
              />
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={close_disable_modal}
                variant="outline"
                className="flex-1 border-line text-dim hover:bg-panel-sunk hover:text-ink"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={loading || disable_code.length !== 6}
                variant="destructive"
                className="flex-1"
              >
                {loading ? 'Disabling...' : 'Disable 2FA'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Recovery Codes Modal */}
      <Dialog open={show_recovery_codes_modal} onOpenChange={close_recovery_codes_modal}>
        <DialogContent className="rounded-[18px] border-line bg-panel sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="text-[19px] font-semibold tracking-[-0.015em] text-ink">
              Save your recovery codes
            </DialogTitle>
            <DialogDescription className="text-[13px] text-dim">
              Store these somewhere safe. Each code works exactly once, and they are the
              only way back in if you lose your authenticator.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <ul className="grid grid-cols-2 gap-2 rounded-[11px] border border-line bg-panel-sunk p-3">
              {recovery_codes.map((code, index) => (
                <li
                  key={index}
                  className="num min-w-0 break-all rounded-[8px] border border-line2 bg-panel px-2 py-2 text-center text-[13px] tracking-wide text-ink"
                >
                  {code}
                </li>
              ))}
            </ul>

            <StatusNote tone="gold" icon={TriangleAlert}>
              These codes are shown only once. Copy or download them before you close this window.
            </StatusNote>

            <div className="flex flex-col gap-2 sm:flex-row">
              <Button
                type="button"
                onClick={copy_recovery_codes}
                variant="outline"
                className="flex-1 border-line text-ink hover:bg-panel-sunk hover:text-ink"
              >
                {recovery_copied ? <Check className="size-4"/> : <Copy className="size-4"/>}
                {recovery_copied ? 'Copied' : 'Copy codes'}
              </Button>
              <Button type="button" onClick={download_recovery_codes} className="flex-1">
                <Download className="size-4"/>
                Download codes
              </Button>
            </div>

            <Button
              type="button"
              onClick={close_recovery_codes_modal}
              variant="ghost"
              className="w-full text-dim hover:bg-panel-sunk hover:text-ink"
            >
              I've saved my codes
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Edit Tag Modal */}
      <Dialog open={show_edit_tag_modal} onOpenChange={set_show_edit_tag_modal}>
        <DialogContent className="rounded-[18px] border-line bg-panel sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="text-[19px] font-semibold tracking-[-0.015em] text-ink">
              Edit tag
            </DialogTitle>
            <DialogDescription className="text-[13px] text-dim">
              Update the tag name and colour.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_edit_tag} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit_tag_name" className="text-[13px] font-medium text-ink">
                Tag name
              </Label>
              <Input
                id="edit_tag_name"
                value={edit_tag_name}
                onChange={(e) => set_edit_tag_name(e.target.value)}
                required
                className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink placeholder:text-faint"
                placeholder="Enter tag name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit_tag_color" className="text-[13px] font-medium text-ink">
                Tag colour
              </Label>
              <div className="flex items-center gap-3 rounded-[11px] border border-line2 bg-panel-sunk px-3 py-2.5">
                <input
                  id="edit_tag_color"
                  type="color"
                  value={edit_tag_color}
                  onChange={(e) => set_edit_tag_color(e.target.value)}
                  className="h-9 w-14 cursor-pointer rounded-[8px] border border-line bg-panel"
                />
                <span className="num text-[13px] uppercase tracking-wide text-dim">
                  {edit_tag_color}
                </span>
              </div>
            </div>

            {tag_error && (
              <StatusNote tone="down" icon={TriangleAlert}>{tag_error}</StatusNote>
            )}

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={() => set_show_edit_tag_modal(false)}
                variant="outline"
                className="flex-1 border-line text-dim hover:bg-panel-sunk hover:text-ink"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={tags_loading || !edit_tag_name}
                className="flex-1"
              >
                {tags_loading ? 'Updating...' : 'Update tag'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Tag Modal */}
      <Dialog open={show_delete_tag_modal} onOpenChange={set_show_delete_tag_modal}>
        <DialogContent className="rounded-[18px] border-line bg-panel sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="text-[19px] font-semibold tracking-[-0.015em] text-ink">
              Delete tag
            </DialogTitle>
            <DialogDescription className="text-[13px] text-dim">
              This removes the tag from every note. It cannot be undone.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {current_tag && (
              <div className="flex items-center gap-3 rounded-[11px] border border-line2 bg-panel-sunk px-3 py-2.5">
                <span
                  className="size-3.5 shrink-0 rounded-full border border-line"
                  style={{ backgroundColor: current_tag.color }}
                  aria-hidden="true"
                />
                <span className="truncate text-sm text-ink">{current_tag.name}</span>
              </div>
            )}

            {tag_error && (
              <StatusNote tone="down" icon={TriangleAlert}>{tag_error}</StatusNote>
            )}

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={() => set_show_delete_tag_modal(false)}
                variant="outline"
                className="flex-1 border-line text-dim hover:bg-panel-sunk hover:text-ink"
              >
                Cancel
              </Button>
              <Button
                onClick={handle_delete_tag}
                disabled={tags_loading}
                variant="destructive"
                className="flex-1"
              >
                {tags_loading ? 'Deleting...' : 'Delete tag'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
