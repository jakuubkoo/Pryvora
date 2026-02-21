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
  const [error, set_error] = useState('')
  const [loading, set_loading] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()

  const handle_submit = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_loading(true)

    try
    {
      await login(email, password)
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

