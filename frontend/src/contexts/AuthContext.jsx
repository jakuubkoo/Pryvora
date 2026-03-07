import { createContext, useContext, useState, useEffect } from 'react'

const AuthContext = createContext(null)

// Try to restore access token from sessionStorage on module load
let access_token = sessionStorage.getItem('access_token') || null
let refresh_promise = null
let auth_check_promise = null

// Helper to store token in both memory and sessionStorage
const set_access_token = (token) =>
{
  access_token = token
  if (token)
  {
    sessionStorage.setItem('access_token', token)
  }
  else
  {
    sessionStorage.removeItem('access_token')
  }
}

export function AuthProvider({ children })
{
  const [user, set_user] = useState(null)
  const [loading, set_loading] = useState(true)

  useEffect(() =>
  {
    let is_mounted = true

    const init_auth = async () =>
    {
      if (is_mounted)
      {
        // If auth check is already in progress, wait for it
        if (auth_check_promise)
        {
          await auth_check_promise
          return
        }

        auth_check_promise = check_auth()
        await auth_check_promise
        auth_check_promise = null
      }
    }

    init_auth()

    return () =>
    {
      is_mounted = false
    }
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

    if (response.status === 401)
    {
      // If a refresh is already in progress, wait for it
      if (refresh_promise)
      {
        try
        {
          await refresh_promise
          // Retry the original request with the new token
          headers['Authorization'] = `Bearer ${access_token}`
          response = await fetch(url, {
            ...options,
            headers,
            credentials: 'include',
          })
          return response
        }
        catch (error)
        {
          throw error
        }
      }

      // Start a new refresh
      refresh_promise = fetch(`${import.meta.env.VITE_API_URL}/api/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
      })
        .then(async (refresh_response) =>
        {
          if (refresh_response.ok)
          {
            const refresh_data = await refresh_response.json()
            set_access_token(refresh_data.token)
            return true
          }
          else
          {
            set_access_token(null)
            set_user(null)
            throw new Error('Session expired')
          }
        })
        .finally(() =>
        {
          refresh_promise = null
        })

      try
      {
        await refresh_promise

        headers['Authorization'] = `Bearer ${access_token}`
        response = await fetch(url, {
          ...options,
          headers,
          credentials: 'include',
        })
      }
      catch (error)
      {
        throw error
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
        set_access_token(null)
      }
    }
    catch (error)
    {
      console.error('Auth check failed:', error)
      set_user(null)
      set_access_token(null)
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

    set_access_token(data.token)

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
    set_access_token(data.token)

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
      set_access_token(null)
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

