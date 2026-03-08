import { useState, useEffect } from 'react'
import { Edit, Trash2 } from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
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
  const [recovery_codes, set_recovery_codes] = useState([])
  const [show_recovery_codes_modal, set_show_recovery_codes_modal] = useState(false)

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

        <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader>
            <CardTitle className="text-[#e5e5e5]">Change Password</CardTitle>
            <CardDescription className="text-[#888888]">
              Update your password to keep your account secure
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handle_change_password} className="space-y-4">
              {password_error && (
                <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                  <p className="text-sm text-red-400">{password_error}</p>
                </div>
              )}
              {password_success && (
                <div className="p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                  <p className="text-sm text-green-400">{password_success}</p>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="current_password" className="text-sm text-[#e5e5e5]">
                  Current Password
                </Label>
                <Input
                  id="current_password"
                  type="password"
                  value={current_password}
                  onChange={(e) => set_current_password(e.target.value)}
                  required
                  className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                  placeholder="Enter your current password"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="new_password" className="text-sm text-[#e5e5e5]">
                  New Password
                </Label>
                <Input
                  id="new_password"
                  type="password"
                  value={new_password}
                  onChange={(e) => set_new_password(e.target.value)}
                  required
                  className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                  placeholder="Enter your new password"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="confirm_password" className="text-sm text-[#e5e5e5]">
                  Confirm New Password
                </Label>
                <Input
                  id="confirm_password"
                  type="password"
                  value={confirm_password}
                  onChange={(e) => set_confirm_password(e.target.value)}
                  required
                  className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                  placeholder="Confirm your new password"
                />
              </div>

              <Button
                type="submit"
                disabled={password_loading}
                className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
              >
                {password_loading ? 'Changing Password...' : 'Change Password'}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Tags Management */}
        <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader>
            <CardTitle className="text-[#e5e5e5]">Manage Tags</CardTitle>
            <CardDescription className="text-[#888888]">
              View, edit, and delete your tags
            </CardDescription>
          </CardHeader>
          <CardContent>
            {tag_success && (
              <Alert className="mb-4 border-green-500/50 bg-green-500/10">
                <AlertDescription className="text-green-400">
                  {tag_success}
                </AlertDescription>
              </Alert>
            )}

            {tag_error && (
              <Alert className="mb-4 border-red-500/50 bg-red-500/10">
                <AlertDescription className="text-red-400">
                  {tag_error}
                </AlertDescription>
              </Alert>
            )}

            {tags_loading ? (
              <p className="text-sm text-[#888888]">Loading tags...</p>
            ) : tags.length === 0 ? (
              <p className="text-sm text-[#888888]">No tags created yet. Create tags when adding notes.</p>
            ) : (
              <div className="space-y-2">
                {tags.map(tag => (
                  <div
                    key={tag.id}
                    className="flex items-center justify-between p-3 rounded-lg bg-[#1a1a1a] border border-[#2a2a2a]"
                  >
                    <div className="flex items-center gap-3">
                      <div
                        className="size-6 rounded-full border border-[#2a2a2a]"
                        style={{ backgroundColor: tag.color }}
                      />
                      <span className="text-sm text-[#e5e5e5]">{tag.name}</span>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        onClick={() => open_edit_tag_modal(tag)}
                        variant="ghost"
                        size="sm"
                        className="text-[#888888] hover:text-[#e5e5e5]"
                      >
                        <Edit className="size-4"/>
                      </Button>
                      <Button
                        onClick={() => open_delete_tag_modal(tag)}
                        variant="ghost"
                        size="sm"
                        className="text-red-400 hover:text-red-300"
                      >
                        <Trash2 className="size-4"/>
                      </Button>
                    </div>
                  </div>
                ))}
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

      {/* Recovery Codes Modal */}
      <Dialog open={show_recovery_codes_modal} onOpenChange={close_recovery_codes_modal}>
        <DialogContent className="sm:max-w-[500px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5]">Save Your Recovery Codes</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Store these recovery codes in a safe place. Each code can only be used once.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            <div className="p-4 rounded-lg bg-[#1a1a1a] border border-[#2a2a2a]">
              <div className="grid grid-cols-2 gap-2 font-mono text-sm text-[#e5e5e5]">
                {recovery_codes.map((code, index) => (
                  <div key={index} className="p-2 bg-[#0a0a0a] rounded text-center">
                    {code}
                  </div>
                ))}
              </div>
            </div>

            <div className="p-3 rounded-lg bg-yellow-500/10 border border-yellow-500/20">
              <p className="text-sm text-yellow-400">
                ⚠️ These codes will only be shown once. Make sure to save them now!
              </p>
            </div>

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={copy_recovery_codes}
                variant="outline"
                className="flex-1 border-[#2a2a2a] text-[#e5e5e5] hover:bg-[#1a1a1a]"
              >
                Copy Codes
              </Button>
              <Button
                type="button"
                onClick={download_recovery_codes}
                className="flex-1 bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
              >
                Download Codes
              </Button>
            </div>

            <Button
              type="button"
              onClick={close_recovery_codes_modal}
              variant="ghost"
              className="w-full text-[#888888] hover:text-[#e5e5e5]"
            >
              I've Saved My Codes
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Edit Tag Modal */}
      <Dialog open={show_edit_tag_modal} onOpenChange={set_show_edit_tag_modal}>
        <DialogContent className="sm:max-w-[425px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5]">Edit Tag</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Update the tag name and color
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_edit_tag} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="edit_tag_name" className="text-sm text-[#e5e5e5]">
                Tag Name
              </Label>
              <Input
                id="edit_tag_name"
                value={edit_tag_name}
                onChange={(e) => set_edit_tag_name(e.target.value)}
                required
                className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                placeholder="Enter tag name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit_tag_color" className="text-sm text-[#e5e5e5]">
                Tag Color
              </Label>
              <div className="flex gap-2 items-center">
                <input
                  id="edit_tag_color"
                  type="color"
                  value={edit_tag_color}
                  onChange={(e) => set_edit_tag_color(e.target.value)}
                  className="h-10 w-20 rounded border border-[#2a2a2a] bg-[#1a1a1a] cursor-pointer"
                />
                <div className="flex items-center gap-2 flex-1">
                  <div
                    className="size-6 rounded-full border border-[#2a2a2a]"
                    style={{ backgroundColor: edit_tag_color }}
                  />
                  <span className="text-sm text-[#888888]">{edit_tag_color}</span>
                </div>
              </div>
            </div>

            {tag_error && (
              <p className="text-sm text-red-400">{tag_error}</p>
            )}

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={() => set_show_edit_tag_modal(false)}
                variant="ghost"
                className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={tags_loading || !edit_tag_name}
                className="flex-1 bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
              >
                {tags_loading ? 'Updating...' : 'Update Tag'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Tag Modal */}
      <Dialog open={show_delete_tag_modal} onOpenChange={set_show_delete_tag_modal}>
        <DialogContent className="sm:max-w-[425px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5]">Delete Tag</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Are you sure you want to delete this tag? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>

          {current_tag && (
            <div className="py-4">
              <div className="flex items-center gap-3 p-3 rounded-lg bg-[#1a1a1a] border border-[#2a2a2a]">
                <div
                  className="size-6 rounded-full border border-[#2a2a2a]"
                  style={{ backgroundColor: current_tag.color }}
                />
                <span className="text-sm text-[#e5e5e5]">{current_tag.name}</span>
              </div>
            </div>
          )}

          {tag_error && (
            <p className="text-sm text-red-400 mb-4">{tag_error}</p>
          )}

          <div className="flex gap-2">
            <Button
              type="button"
              onClick={() => set_show_delete_tag_modal(false)}
              variant="ghost"
              className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
            >
              Cancel
            </Button>
            <Button
              onClick={handle_delete_tag}
              disabled={tags_loading}
              className="flex-1 bg-red-500 text-white hover:bg-red-600"
            >
              {tags_loading ? 'Deleting...' : 'Delete Tag'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}

