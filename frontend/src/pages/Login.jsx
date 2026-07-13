import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { AlertCircle, ArrowLeft, Lock } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'
import { cn } from '@/lib/utils'

const field_class = 'h-11 w-full rounded-[11px] border border-line bg-panel-sunk px-3.5 text-[15px] text-ink shadow-none placeholder:text-faint disabled:opacity-50'

const submit_class = 'h-11 w-full rounded-[11px] bg-accent text-[14px] font-bold text-on-accent shadow-none transition-opacity hover:bg-accent hover:opacity-90'

const quiet_class = 'inline-flex items-center justify-center gap-1.5 rounded-[11px] px-3 py-2 text-[13px] font-semibold text-dim transition-colors hover:text-ink'

function Wordmark()
{
  return (
    <div className="flex items-center justify-center gap-2.5">
      <span className="grid h-9 w-9 place-items-center rounded-lg bg-ink text-[19px] font-bold text-paper">P</span>
      <span className="text-[24px] font-bold tracking-[-0.01em] text-ink">Pryvora</span>
    </div>
  )
}

function ErrorBox({ message })
{
  return (
    <div
      role="alert"
      className="flex items-start gap-2.5 rounded-[11px] border p-3.5"
      style={{
        borderColor: 'color-mix(in srgb, var(--down) 30%, transparent)',
        background: 'color-mix(in srgb, var(--down) 8%, transparent)',
      }}
    >
      <AlertCircle className="mt-px h-4 w-4 flex-none text-down"/>
      <p className="text-[13px] leading-snug text-down">{message}</p>
    </div>
  )
}

export default function Login()
{
  const [email, set_email] = useState('')
  const [password, set_password] = useState('')
  const [otp_code, set_otp_code] = useState('')
  const [error, set_error] = useState('')
  const [loading, set_loading] = useState(false)
  const [requires_2fa, set_requires_2fa] = useState(false)
  const [use_recovery_code, set_use_recovery_code] = useState(false)
  const { login, verify_2fa } = useAuth()
  const navigate = useNavigate()
  const reduced_motion = use_reduced_motion()

  const rise = reduced_motion
    ? {}
    : {
      initial: { opacity: 0, y: 8 },
      animate: { opacity: 1, y: 0 },
      transition: { duration: 0.35, ease: [0.22, 1, 0.36, 1] },
    }

  const handle_submit = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_loading(true)

    try
    {
      const result = await login(email, password)
      if (result.requires_2fa)
      {
        set_requires_2fa(true)
      }
      else
      {
        navigate('/dashboard')
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

  const handle_2fa_submit = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_loading(true)

    try
    {
      await verify_2fa(otp_code, use_recovery_code)
      navigate('/dashboard')
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

  if (requires_2fa)
  {
    return (
      <div className="flex min-h-screen items-center justify-center bg-paper px-5 py-10">
        <motion.main {...rise} className="w-full max-w-[420px]">

          <div className="mb-8">
            <Wordmark/>
          </div>

          <div className="panel p-6 sm:p-8">
            <p className="eyebrow mb-2">Step 2 of 2</p>
            <h1 className="text-[22px] font-bold tracking-[-0.01em] text-ink">
              Two-factor authentication
            </h1>
            <p className="mt-1.5 text-[14px] leading-relaxed text-dim">
              {use_recovery_code
                ? 'Enter one of the recovery codes you saved when you set up two-factor.'
                : 'Enter the 6-digit code from your authenticator app.'}
            </p>

            <form onSubmit={handle_2fa_submit} className="mt-7 space-y-5">
              <div>
                <Label htmlFor="otp" className="eyebrow mb-2 block">
                  {use_recovery_code ? 'Recovery code' : 'Verification code'}
                </Label>
                <Input
                  id="otp"
                  type="text"
                  autoFocus
                  autoComplete="one-time-code"
                  inputMode={use_recovery_code ? 'text' : 'numeric'}
                  placeholder={use_recovery_code ? 'XXXXXXXX' : '000000'}
                  value={otp_code}
                  onChange={(e) => {
                    if (use_recovery_code)
                    {
                      set_otp_code(e.target.value.toUpperCase().replace(/[^A-F0-9]/g, '').slice(0, 8))
                    }
                    else
                    {
                      set_otp_code(e.target.value.replace(/\D/g, '').slice(0, 6))
                    }
                  }}
                  required
                  maxLength={use_recovery_code ? 8 : 6}
                  className={cn(
                    'num h-16 w-full rounded-[11px] border border-line bg-panel-sunk text-center text-ink shadow-none placeholder:text-faint',
                    use_recovery_code
                      ? 'text-[24px] tracking-[0.22em]'
                      : 'text-[28px] tracking-[0.28em]'
                  )}
                />
              </div>

              {error && <ErrorBox message={error}/>}

              <Button
                type="submit"
                disabled={loading || (use_recovery_code ? otp_code.length !== 8 : otp_code.length !== 6)}
                className={submit_class}
              >
                {loading ? 'Verifying…' : 'Verify'}
              </Button>

              <div className="flex flex-col items-center gap-1 border-t border-line2 pt-4">
                <button
                  type="button"
                  onClick={() => {
                    set_use_recovery_code(!use_recovery_code)
                    set_otp_code('')
                    set_error('')
                  }}
                  className={quiet_class}
                >
                  {use_recovery_code ? 'Use authenticator code' : 'Use a recovery code'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    set_requires_2fa(false)
                    set_use_recovery_code(false)
                    set_otp_code('')
                  }}
                  className={quiet_class}
                >
                  <ArrowLeft className="h-3.5 w-3.5"/>
                  Back to login
                </button>
              </div>
            </form>
          </div>
        </motion.main>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-paper px-5 py-10">
      <motion.main {...rise} className="w-full max-w-[420px]">

        <div className="mb-8">
          <Wordmark/>
        </div>

        <div className="panel p-6 sm:p-8">
          <p className="eyebrow mb-2">Sign in</p>
          <h1 className="text-[22px] font-bold tracking-[-0.01em] text-ink">
            Welcome back
          </h1>
          <p className="mt-1.5 text-[14px] leading-relaxed text-dim">
            Your notes, tasks and calendar — kept in one place, on your own server.
          </p>

          <form onSubmit={handle_submit} className="mt-7 space-y-5">
            <div>
              <Label htmlFor="email" className="eyebrow mb-2 block">
                Email address
              </Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => set_email(e.target.value)}
                required
                disabled={loading}
                className={field_class}
              />
            </div>

            <div>
              <div className="mb-2 flex items-baseline justify-between gap-3">
                <Label htmlFor="password" className="eyebrow">
                  Password
                </Label>
                <span className="text-[11px] font-semibold text-faint">
                  Reset not available yet
                </span>
              </div>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => set_password(e.target.value)}
                required
                disabled={loading}
                className={field_class}
              />
            </div>

            {error && <ErrorBox message={error}/>}

            <Button
              type="submit"
              disabled={loading}
              className={submit_class}
            >
              {loading ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>

          <div className="mt-6 border-t border-line2 pt-5 text-center text-[14px] text-dim">
            Don&apos;t have an account?{' '}
            <Link to="/register" className="font-semibold text-accent underline-offset-4 hover:underline">
              Create one
            </Link>
          </div>
        </div>

        <p className="mx-auto mt-7 flex max-w-[360px] items-start justify-center gap-2 text-[12px] leading-relaxed text-faint">
          <Lock className="mt-0.5 h-3.5 w-3.5 flex-none"/>
          <span>
            Self-hosted. Your data is encrypted at rest with AES-256-GCM and passwords are
            hashed with Argon2id — a database breach yields ciphertext.
          </span>
        </p>
      </motion.main>
    </div>
  )
}
