import { createContext, useContext, useState, useEffect } from 'react'

const AuthContext = createContext(null)

let access_token = null

export function AuthProvider({ children })
{
  const [user, set_user] = useState(null)
  const [loading, set_loading] = useState(true)

  useEffect(() =>
  {
    check_auth()
  }, [])

  const api_request = async (url, options = {}) =>
  {
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    }

    if (access_token)
    {
      headers['Authorization'] = `Bearer ${access_token}`
    }

    let response = await fetch(url, {
      ...options,
      headers,
      credentials: 'include',
    })

    if (response.status === 401 && access_token)
    {
      const refresh_response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
      })

      if (refresh_response.ok)
      {
        const refresh_data = await refresh_response.json()
        access_token = refresh_data.token

        headers['Authorization'] = `Bearer ${access_token}`
        response = await fetch(url, {
          ...options,
          headers,
          credentials: 'include',
        })
      }
      else
      {
        access_token = null
        set_user(null)
        throw new Error('Session expired')
      }
    }

    return response
  }

  const check_auth = async () =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/user/me`)

      if (response.ok)
      {
        const data = await response.json()
        set_user(data)
      }
      else
      {
        set_user(null)
        access_token = null
      }
    }
    catch (error)
    {
      console.error('Auth check failed:', error)
      set_user(null)
      access_token = null
    }
    finally
    {
      set_loading(false)
    }
  }

  const register = async (first_name, last_name, email, password) =>
  {
    const response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        firstName: first_name,
        lastName: last_name,
        email,
        password,
        confirmPassword: password
      }),
    })

    if (!response.ok)
    {
      const error = await response.json()
      throw new Error(error.error || 'Registration failed')
    }

    await login(email, password)
  }

  const login = async (email, password) =>
  {
    const response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ email, password }),
    })

    if (!response.ok)
    {
      const error = await response.json()
      throw new Error(error.error || 'Login failed')
    }

    const data = await response.json()

    if (data.requires_2fa)
    {
      return { requires_2fa: true }
    }

    access_token = data.token

    const user_response = await api_request(`${import.meta.env.VITE_API_URL}/api/user/me`)
    if (user_response.ok)
    {
      const user_data = await user_response.json()
      set_user(user_data)
    }

    return data
  }

  const verify_2fa = async (code, is_recovery_code = false) =>
  {
    const response = await fetch(`${import.meta.env.VITE_API_URL}/api/auth/verify-2fa`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        code,
        is_recovery_code
      }),
    })

    if (!response.ok)
    {
      const error = await response.json()
      throw new Error(error.error || '2FA verification failed')
    }

    const data = await response.json()
    access_token = data.token

    const user_response = await api_request(`${import.meta.env.VITE_API_URL}/api/user/me`)
    if (user_response.ok)
    {
      const user_data = await user_response.json()
      set_user(user_data)
    }

    return data
  }

  const logout = async () =>
  {
    try
    {
      await fetch(`${import.meta.env.VITE_API_URL}/api/auth/logout`, {
        method: 'POST',
        credentials: 'include',
      })
    }
    catch (error)
    {
      console.error('Logout failed:', error)
    }
    finally
    {
      access_token = null
      set_user(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, register, check_auth, verify_2fa, api_request }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth()
{
  const context = useContext(AuthContext)
  if (!context)
  {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}

