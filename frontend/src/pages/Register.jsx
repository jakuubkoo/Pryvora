import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { AlertCircle, Lock } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'

const field_class = 'h-11 w-full rounded-[11px] border border-line bg-panel-sunk px-3.5 text-[15px] text-ink shadow-none placeholder:text-faint disabled:opacity-50'

const submit_class = 'h-11 w-full rounded-[11px] bg-accent text-[14px] font-bold text-on-accent shadow-none transition-opacity hover:bg-accent hover:opacity-90'

export default function Register()
{
  const [first_name, set_first_name] = useState('')
  const [last_name, set_last_name] = useState('')
  const [email, set_email] = useState('')
  const [password, set_password] = useState('')
  const [password_confirmation, set_password_confirmation] = useState('')
  const [error, set_error] = useState('')
  const [loading, set_loading] = useState(false)
  const { register } = useAuth()
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

    if (!first_name || !last_name || !email || !password || !password_confirmation)
    {
      set_error('All fields are required')
      return
    }

    if (password !== password_confirmation)
    {
      set_error('Passwords do not match')
      return
    }

    if (password.length < 6)
    {
      set_error('Password must be at least 6 characters')
      return
    }

    set_loading(true)

    try
    {
      await register(first_name, last_name, email, password)
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

  return (
    <div className="flex min-h-screen items-center justify-center bg-paper px-5 py-10">
      <motion.main {...rise} className="w-full max-w-[420px]">

        <div className="mb-8 flex items-center justify-center gap-2.5">
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-ink text-[19px] font-bold text-paper">P</span>
          <span className="text-[24px] font-bold tracking-[-0.01em] text-ink">Pryvora</span>
        </div>

        <div className="panel p-6 sm:p-8">
          <p className="eyebrow mb-2">New account</p>
          <h1 className="text-[22px] font-bold tracking-[-0.01em] text-ink">
            Create your archive
          </h1>
          <p className="mt-1.5 text-[14px] leading-relaxed text-dim">
            One account for your notes, tasks and calendar. It stays on your server.
          </p>

          <form onSubmit={handle_submit} className="mt-7 space-y-5">
            {error && (
              <div
                role="alert"
                className="flex items-start gap-2.5 rounded-[11px] border p-3.5"
                style={{
                  borderColor: 'color-mix(in srgb, var(--down) 30%, transparent)',
                  background: 'color-mix(in srgb, var(--down) 8%, transparent)',
                }}
              >
                <AlertCircle className="mt-px h-4 w-4 flex-none text-down"/>
                <p className="text-[13px] leading-snug text-down">{error}</p>
              </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <Label htmlFor="first_name" className="eyebrow mb-2 block">
                  First name
                </Label>
                <Input
                  id="first_name"
                  type="text"
                  autoComplete="given-name"
                  placeholder="Ada"
                  value={first_name}
                  onChange={(e) => set_first_name(e.target.value)}
                  disabled={loading}
                  className={field_class}
                />
              </div>

              <div>
                <Label htmlFor="last_name" className="eyebrow mb-2 block">
                  Last name
                </Label>
                <Input
                  id="last_name"
                  type="text"
                  autoComplete="family-name"
                  placeholder="Lovelace"
                  value={last_name}
                  onChange={(e) => set_last_name(e.target.value)}
                  disabled={loading}
                  className={field_class}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="email" className="eyebrow mb-2 block">
                Email
              </Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => set_email(e.target.value)}
                disabled={loading}
                className={field_class}
              />
            </div>

            <div>
              <Label htmlFor="password" className="eyebrow mb-2 block">
                Password
              </Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => set_password(e.target.value)}
                disabled={loading}
                aria-describedby="password_hint"
                className={field_class}
              />
              <p id="password_hint" className="mt-2 text-[12px] text-faint">
                At least 6 characters.
              </p>
            </div>

            <div>
              <Label htmlFor="password_confirmation" className="eyebrow mb-2 block">
                Confirm password
              </Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                placeholder="••••••••"
                value={password_confirmation}
                onChange={(e) => set_password_confirmation(e.target.value)}
                disabled={loading}
                className={field_class}
              />
            </div>

            <Button
              type="submit"
              disabled={loading}
              className={submit_class}
            >
              {loading ? 'Creating account…' : 'Create account'}
            </Button>
          </form>

          <div className="mt-6 border-t border-line2 pt-5 text-center text-[14px] text-dim">
            Already have an account?{' '}
            <Link to="/login" className="font-semibold text-accent underline-offset-4 hover:underline">
              Sign in
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
