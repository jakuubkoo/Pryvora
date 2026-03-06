import { useState, useEffect } from 'react'
import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAuth } from '@/contexts/AuthContext'

export default function Settings()
{
  const { user, api_request } = useAuth()
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

  useEffect(() =>
  {
    if (user)
    {
      set_two_factor_enabled(user.two_factor_enabled)
    }
  }, [user])

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

      set_success('2FA enabled successfully! Your account is now more secure.')
      set_two_factor_enabled(true)
      set_show_setup_modal(false)
      set_qr_code('')
      set_secret('')
      set_verification_code('')
      set_setup_step(1)
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

  return (
    <AppLayout title="Settings">
      <div className="p-6 space-y-6">
        <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader>
            <CardTitle className="text-[#e5e5e5]">Two-Factor Authentication</CardTitle>
            <CardDescription className="text-[#888888]">
              Add an extra layer of security to your account
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {error && (
              <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-sm text-red-400">{error}</p>
              </div>
            )}
            {success && (
              <div className="p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                <p className="text-sm text-green-400">{success}</p>
              </div>
            )}

            {!two_factor_enabled && (
              <div className="space-y-4">
                <p className="text-sm text-[#888888]">
                  Two-factor authentication is currently disabled. Enable it to secure your account with a time-based one-time password (TOTP).
                </p>
                <Button
                  onClick={handle_setup_2fa}
                  disabled={loading}
                  className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
                >
                  {loading ? 'Setting up...' : 'Enable 2FA'}
                </Button>
              </div>
            )}

            {two_factor_enabled && (
              <div className="space-y-4">
                <div className="flex items-center gap-3 p-4 rounded-lg bg-green-500/10 border border-green-500/20">
                  <div className="h-3 w-3 bg-green-500 rounded-full animate-pulse"></div>
                  <div>
                    <p className="text-sm font-medium text-green-400">Two-factor authentication is active</p>
                    <p className="text-xs text-[#888888] mt-1">Your account is protected with TOTP</p>
                  </div>
                </div>
                <Button
                  onClick={() => set_show_disable_modal(true)}
                  variant="destructive"
                  className="bg-red-600 hover:bg-red-700"
                >
                  Disable 2FA
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Setup 2FA Modal */}
      <Dialog open={show_setup_modal} onOpenChange={close_setup_modal}>
        <DialogContent className="sm:max-w-[500px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5] text-xl">Enable Two-Factor Authentication</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Follow these steps to secure your account
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6 py-4">
            {/* Progress Indicator */}
            <div className="flex items-center justify-center gap-2">
              <div className={`flex items-center justify-center w-8 h-8 rounded-full ${setup_step >= 1 ? 'bg-[#e5e5e5] text-[#0a0a0a]' : 'bg-[#1a1a1a] text-[#666666]'} text-sm font-medium`}>
                1
              </div>
              <div className={`h-0.5 w-12 ${setup_step >= 2 ? 'bg-[#e5e5e5]' : 'bg-[#1a1a1a]'}`}></div>
              <div className={`flex items-center justify-center w-8 h-8 rounded-full ${setup_step >= 2 ? 'bg-[#e5e5e5] text-[#0a0a0a]' : 'bg-[#1a1a1a] text-[#666666]'} text-sm font-medium`}>
                2
              </div>
              <div className={`h-0.5 w-12 ${setup_step >= 3 ? 'bg-[#e5e5e5]' : 'bg-[#1a1a1a]'}`}></div>
              <div className={`flex items-center justify-center w-8 h-8 rounded-full ${setup_step >= 3 ? 'bg-[#e5e5e5] text-[#0a0a0a]' : 'bg-[#1a1a1a] text-[#666666]'} text-sm font-medium`}>
                3
              </div>
            </div>

            {error && (
              <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-sm text-red-400">{error}</p>
              </div>
            )}

            {/* Step 1: Scan QR Code */}
            {setup_step === 1 && (
              <div className="space-y-4">
                <div className="text-center space-y-2">
                  <h3 className="text-sm font-medium text-[#e5e5e5]">Step 1: Scan QR Code</h3>
                  <p className="text-xs text-[#888888]">
                    Use Google Authenticator, Authy, or any TOTP app
                  </p>
                </div>
                <div className="flex justify-center">
                  <div className="bg-white p-4 rounded-lg">
                    <img src={qr_code} alt="QR Code" className="w-56 h-56"/>
                  </div>
                </div>
                <Button
                  onClick={() => set_setup_step(2)}
                  className="w-full bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
                >
                  I've scanned the code
                </Button>
              </div>
            )}

            {/* Step 2: Manual Entry */}
            {setup_step === 2 && (
              <div className="space-y-4">
                <div className="text-center space-y-2">
                  <h3 className="text-sm font-medium text-[#e5e5e5]">Step 2: Manual Entry (Optional)</h3>
                  <p className="text-xs text-[#888888]">
                    Can't scan? Enter this secret manually in your app
                  </p>
                </div>
                <div className="space-y-2">
                  <code className="block bg-[#1a1a1a] p-4 rounded-lg text-[#e5e5e5] text-center text-sm break-all border border-[#2a2a2a]">
                    {secret}
                  </code>
                  <p className="text-xs text-center text-[#666666]">Keep this secret safe and never share it</p>
                </div>
                <div className="flex gap-2">
                  <Button
                    onClick={() => set_setup_step(1)}
                    variant="ghost"
                    className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
                  >
                    Back
                  </Button>
                  <Button
                    onClick={() => set_setup_step(3)}
                    className="flex-1 bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
                  >
                    Continue
                  </Button>
                </div>
              </div>
            )}

            {/* Step 3: Verify */}
            {setup_step === 3 && (
              <form onSubmit={handle_enable_2fa} className="space-y-4">
                <div className="text-center space-y-2">
                  <h3 className="text-sm font-medium text-[#e5e5e5]">Step 3: Verify Code</h3>
                  <p className="text-xs text-[#888888]">
                    Enter the 6-digit code from your authenticator app
                  </p>
                </div>
                <div className="space-y-2">
                  <Input
                    type="text"
                    placeholder="000000"
                    value={verification_code}
                    onChange={(e) => set_verification_code(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    required
                    maxLength={6}
                    className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666] text-center text-2xl tracking-widest"
                    autoFocus
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    onClick={() => set_setup_step(2)}
                    variant="ghost"
                    className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
                  >
                    Back
                  </Button>
                  <Button
                    type="submit"
                    disabled={loading || verification_code.length !== 6}
                    className="flex-1 bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
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
        <DialogContent className="sm:max-w-[425px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5]">Disable Two-Factor Authentication</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Enter your verification code to disable 2FA
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_disable_2fa} className="space-y-4 py-4">
            {error && (
              <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-sm text-red-400">{error}</p>
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="disable_code" className="text-sm text-[#e5e5e5]">
                Verification Code
              </Label>
              <Input
                id="disable_code"
                type="text"
                placeholder="000000"
                value={disable_code}
                onChange={(e) => set_disable_code(e.target.value.replace(/\D/g, '').slice(0, 6))}
                required
                maxLength={6}
                className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666] text-center text-xl tracking-widest"
                autoFocus
              />
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={close_disable_modal}
                variant="ghost"
                className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={loading || disable_code.length !== 6}
                variant="destructive"
                className="flex-1 bg-red-600 hover:bg-red-700"
              >
                {loading ? 'Disabling...' : 'Disable 2FA'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}

