import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '@/contexts/AuthContext'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

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
      <div className="min-h-screen flex items-center justify-center bg-[#131313] p-6 obsidian-bg">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
          className="w-full max-w-[440px]"
        >
          <div className="glass-card rounded-xl p-10 relative">
            <div className="mb-8">
              <h2 className="font-headline text-[1.75rem] font-bold tracking-tight text-[#e5e2e1] mb-2">
                Two-Factor Authentication
              </h2>
              <p className="text-[#c6c5d5] text-[0.9375rem]">
                {use_recovery_code
                  ? 'Enter one of your recovery codes'
                  : 'Enter the 6-digit code from your authenticator app'}
              </p>
            </div>

            <form onSubmit={handle_2fa_submit} className="space-y-6">
              <div className="space-y-2">
                <Label htmlFor="otp" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                  {use_recovery_code ? 'Recovery Code' : 'Verification Code'}
                </Label>
                <Input
                  id="otp"
                  type="text"
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
                  className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none text-center text-[2rem] tracking-[0.3em]"
                />
              </div>

              {error && (
                <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                  <p className="text-sm text-red-400">{error}</p>
                </div>
              )}

              <Button
                type="submit"
                disabled={loading || (use_recovery_code ? otp_code.length !== 8 : otp_code.length !== 6)}
                className="w-full primary-glow-btn text-white font-headline font-bold py-4 rounded-lg transition-transform active:scale-[0.98] mt-2"
              >
                {loading ? 'Verifying...' : 'Verify'}
              </Button>

              <div className="flex flex-col gap-3 pt-2">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    set_use_recovery_code(!use_recovery_code)
                    set_otp_code('')
                    set_error('')
                  }}
                  className="text-[#c6c5d5] hover:text-[#818cf8] transition-colors justify-center"
                >
                  {use_recovery_code ? 'Use authenticator code' : 'Use recovery code'}
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    set_requires_2fa(false)
                    set_use_recovery_code(false)
                    set_otp_code('')
                  }}
                  className="text-[#c6c5d5] hover:text-[#e5e2e1] transition-colors justify-center"
                >
                  Back to login
                </Button>
              </div>
            </form>
          </div>
        </motion.div>
      </div>
    )
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

        {/* Login Card */}
        <motion.div
          className="w-full glass-card rounded-xl p-10 relative"
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.4, delay: 0.2 }}
        >
          {/* Form Header */}
          <div className="mb-8">
            <h2 className="font-headline text-[1.75rem] font-bold tracking-tight text-[#e5e2e1] mb-2">
              Welcome back
            </h2>
            <p className="text-[#c6c5d5] text-[0.9375rem]">
              Your central hub for personal data
            </p>
          </div>

          {/* Login Form */}
          <form onSubmit={handle_submit} className="space-y-6">
            {/* Email Field */}
            <div className="space-y-2">
              <Label htmlFor="email" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider ml-1 block mb-2">
                Email Address
              </Label>
              <div className="relative">
                <Input
                  id="email"
                  type="email"
                  placeholder="name@company.com"
                  value={email}
                  onChange={(e) => set_email(e.target.value)}
                  required
                  disabled={loading}
                  className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
                />
              </div>
            </div>

            {/* Password Field */}
            <div className="space-y-2">
              <div className="flex justify-between items-center ml-1 mb-2">
                <Label htmlFor="password" className="font-label text-[0.6875rem] font-semibold text-[#c6c5d5] uppercase tracking-wider">
                  Password
                </Label>
                <a
                  href="#"
                  className="text-[#818cf8] text-[0.6875rem] font-semibold hover:text-[#bdc2ff] transition-colors"
                  onClick={(e) => e.preventDefault()}
                >
                  Forgot password?
                </a>
              </div>
              <div className="relative">
                <Input
                  id="password"
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => set_password(e.target.value)}
                  required
                  disabled={loading}
                  className="w-full bg-[#0e0e0e] border-none rounded-lg py-4 px-5 text-[#e5e2e1] placeholder:text-[#c6c5d5]/30 focus:ring-1 focus:ring-[#818cf8]/50 transition-all outline-none disabled:opacity-50"
                />
              </div>
            </div>

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

            {/* Submit Action */}
            <Button
              type="submit"
              disabled={loading}
              className={cn(
                'w-full primary-glow-btn text-white font-headline font-bold py-4 rounded-lg transition-transform active:scale-[0.98] mt-2',
                loading && 'opacity-70 cursor-not-allowed'
              )}
            >
              {loading ? 'Signing in...' : 'Login'}
            </Button>
          </form>

          {/* Footer Links */}
          <div className="mt-8 text-center">
            <p className="text-[#c6c5d5] text-[0.9375rem]">
              Don't have an account?
              <Link to="/register" className="text-[#e5e2e1] font-semibold hover:text-[#818cf8] transition-colors ml-1">
                Create an account
              </Link>
            </p>
          </div>
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
