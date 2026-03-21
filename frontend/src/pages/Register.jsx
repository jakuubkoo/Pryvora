import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '@/contexts/AuthContext'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

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
    <div className="min-h-screen flex items-center justify-center bg-[#131313] p-6 obsidian-bg selection:bg-[#818cf8] selection:text-[#131e8c]">
      <motion.main
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
        className="w-full max-w-[440px] flex flex-col items-center"
      >
        {/* Branding Header */}
        <motion.header
          className="mb-12 text-center"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, delay: 0.1 }}
        >
          <h1 className="font-headline font-black text-[2.75rem] leading-none tracking-tighter text-[#e5e2e1] mb-2">
            Pryvora
          </h1>
          <p className="font-label text-[#c6c5d5] text-[0.625rem] uppercase tracking-[0.2em]">
            Privacy-First Personal Hub
          </p>
        </motion.header>

        {/* Register Card */}
        <motion.div
          className="w-full glass-card rounded-xl p-10 relative"
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.4, delay: 0.2 }}
        >
          {/* Form Header */}
          <div className="mb-8">
            <h2 className="font-headline text-[1.75rem] font-bold tracking-tight text-[#e5e2e1] mb-2">
              Create Account
            </h2>
            <p className="text-[#c6c5d5] text-[0.9375rem]">
              Start organizing your personal data
            </p>
          </div>

          {/* Register Form */}
          <form onSubmit={handle_submit} className="space-y-6">
            {/* Error Message */}
            {error && (
              <motion.div
                initial={{ opacity: 0, y: -10 }}
                animate={{ opacity: 1, y: 0 }}
                className="p-4 rounded-lg bg-red-500/10 border border-red-500/20"
              >
                <p className="text-sm text-red-400">{error}</p>
              </motion.div>
            )}

            {/* Name Fields */}
            <div className="space-y-2">
              <Label htmlFor="first_name" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                First Name
              </Label>
              <Input
                id="first_name"
                type="text"
                placeholder="John"
                value={first_name}
                onChange={(e) => set_first_name(e.target.value)}
                disabled={loading}
                className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="last_name" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                Last Name
              </Label>
              <Input
                id="last_name"
                type="text"
                placeholder="Doe"
                value={last_name}
                onChange={(e) => set_last_name(e.target.value)}
                disabled={loading}
                className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
              />
            </div>

            {/* Email Field */}
            <div className="space-y-2">
              <Label htmlFor="email" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                Email
              </Label>
              <Input
                id="email"
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => set_email(e.target.value)}
                disabled={loading}
                className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
              />
            </div>

            {/* Password Fields */}
            <div className="space-y-2">
              <Label htmlFor="password" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                Password
              </Label>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => set_password(e.target.value)}
                disabled={loading}
                className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password_confirmation" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                Confirm Password
              </Label>
              <Input
                id="password_confirmation"
                type="password"
                placeholder="••••••••"
                value={password_confirmation}
                onChange={(e) => set_password_confirmation(e.target.value)}
                disabled={loading}
                className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
              />
            </div>

            {/* Submit Action */}
            <Button
              type="submit"
              className="w-full primary-glow-btn text-white font-headline font-bold py-4 rounded-lg transition-transform active:scale-[0.98] mt-2"
              disabled={loading}
            >
              {loading ? 'Creating account...' : 'Create Account'}
            </Button>

            {/* Separator */}
            <div className="my-6 h-px bg-[#2a2a2a]"></div>

            {/* Footer Links */}
            <div className="text-center text-sm text-[#888888]">
              Already have an account?{' '}
              <Link to="/login" className="underline text-[#e5e5e5]">
                Sign in
              </Link>
            </div>
          </form>
        </motion.div>

        {/* System Status Footer */}
        <motion.footer
          className="mt-12 flex items-center gap-4"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.4, delay: 0.4 }}
        >
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 rounded-full bg-[#10b981] shadow-[0_0_8px_rgba(78,222,163,0.6)]"></div>
            <span className="text-[0.625rem] font-label uppercase tracking-[0.2em] text-[#c6c5d5]">System Operational</span>
          </div>
          <div className="w-px h-3 bg-[#454653]/30"></div>
          <div className="flex items-center gap-1 opacity-50">
            <svg className="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span className="text-[0.625rem] font-label uppercase tracking-[0.2em] text-[#c6c5d5]">Your Data Stays Private</span>
          </div>
        </motion.footer>
      </motion.main>

      {/* Abstract Background Elements */}
      <div className="fixed top-[-10%] right-[-5%] w-[500px] h-[500px] bg-[#818cf8]/10 blur-[120px] rounded-full -z-10"></div>
      <div className="fixed bottom-[-10%] left-[-5%] w-[400px] h-[400px] bg-[#10b981]/5 blur-[100px] rounded-full -z-10"></div>
    </div>
  )
}
