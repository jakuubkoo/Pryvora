import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'

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
    <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#0a0a0a' }}>
      <Card className="w-full max-w-md" style={{ backgroundColor: '#0f0f0f', borderColor: '#1a1a1a' }}>
        <CardHeader>
          <CardTitle className="text-2xl" style={{ color: '#e5e5e5' }}>Create Account</CardTitle>
          <CardDescription style={{ color: '#888888' }}>
            Enter your details to create a new account
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handle_submit} className="space-y-4">
            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            <div className="space-y-2">
              <Label htmlFor="first_name" style={{ color: '#e5e5e5' }}>First Name</Label>
              <Input
                id="first_name"
                type="text"
                placeholder="John"
                value={first_name}
                onChange={(e) => set_first_name(e.target.value)}
                disabled={loading}
                style={{ backgroundColor: '#1a1a1a', borderColor: '#2a2a2a', color: '#e5e5e5' }}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="last_name" style={{ color: '#e5e5e5' }}>Last Name</Label>
              <Input
                id="last_name"
                type="text"
                placeholder="Doe"
                value={last_name}
                onChange={(e) => set_last_name(e.target.value)}
                disabled={loading}
                style={{ backgroundColor: '#1a1a1a', borderColor: '#2a2a2a', color: '#e5e5e5' }}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="email" style={{ color: '#e5e5e5' }}>Email</Label>
              <Input
                id="email"
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => set_email(e.target.value)}
                disabled={loading}
                style={{ backgroundColor: '#1a1a1a', borderColor: '#2a2a2a', color: '#e5e5e5' }}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password" style={{ color: '#e5e5e5' }}>Password</Label>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => set_password(e.target.value)}
                disabled={loading}
                style={{ backgroundColor: '#1a1a1a', borderColor: '#2a2a2a', color: '#e5e5e5' }}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password_confirmation" style={{ color: '#e5e5e5' }}>Confirm Password</Label>
              <Input
                id="password_confirmation"
                type="password"
                placeholder="••••••••"
                value={password_confirmation}
                onChange={(e) => set_password_confirmation(e.target.value)}
                disabled={loading}
                style={{ backgroundColor: '#1a1a1a', borderColor: '#2a2a2a', color: '#e5e5e5' }}
              />
            </div>

            <Button
              type="submit"
              className="w-full"
              disabled={loading}
              style={{ backgroundColor: '#e5e5e5', color: '#0a0a0a' }}
            >
              {loading ? 'Creating account...' : 'Create Account'}
            </Button>

            <Separator style={{ backgroundColor: '#2a2a2a' }}/>

            <div className="text-center text-sm" style={{ color: '#888888' }}>
              Already have an account?{' '}
              <Link to="/login" className="underline" style={{ color: '#e5e5e5' }}>
                Sign in
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

