import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

export default function Login()
{
  const [email, set_email] = useState('')
  const [password, set_password] = useState('')
  const [otp_code, set_otp_code] = useState('')
  const [error, set_error] = useState('')
  const [loading, set_loading] = useState(false)
  const [requires_2fa, set_requires_2fa] = useState(false)
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
      await verify_2fa(otp_code)
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
      <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] p-4">
        <Card className="w-full max-w-md border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-semibold tracking-tight">Two-Factor Authentication</CardTitle>
            <CardDescription className="text-[#888888]">
              Enter the 6-digit code from your authenticator app
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handle_2fa_submit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="otp" className="text-sm text-[#e5e5e5]">Verification Code</Label>
                <Input
                  id="otp"
                  type="text"
                  placeholder="000000"
                  value={otp_code}
                  onChange={(e) => set_otp_code(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  required
                  maxLength={6}
                  className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666] text-center text-2xl tracking-widest"
                />
              </div>
              {error && (
                <p className="text-sm text-red-400">{error}</p>
              )}
              <Button
                type="submit"
                disabled={loading || otp_code.length !== 6}
                className="w-full bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
              >
                {loading ? 'Verifying...' : 'Verify'}
              </Button>
              <Button
                type="button"
                variant="ghost"
                onClick={() => set_requires_2fa(false)}
                className="w-full text-[#888888] hover:text-[#e5e5e5]"
              >
                Back to login
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0a0a0a] p-4">
      <Card className="w-full max-w-md border-[#1a1a1a] bg-[#0f0f0f]">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-semibold tracking-tight">Pryvora</CardTitle>
          <CardDescription className="text-[#888888]">
            Sign in to your account
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handle_submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email" className="text-sm text-[#e5e5e5]">Email</Label>
              <Input
                id="email"
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => set_email(e.target.value)}
                required
                className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password" className="text-sm text-[#e5e5e5]">Password</Label>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => set_password(e.target.value)}
                required
                className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
              />
            </div>
            {error && (
              <p className="text-sm text-red-400">{error}</p>
            )}
            <Button
              type="submit"
              disabled={loading}
              className="w-full bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
            >
              {loading ? 'Signing in...' : 'Sign in'}
            </Button>

            <Separator className="bg-[#2a2a2a]"/>

            <div className="text-center text-sm text-[#888888]">
              Don't have an account?{' '}
              <Link to="/register" className="underline text-[#e5e5e5]">
                Create one
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

