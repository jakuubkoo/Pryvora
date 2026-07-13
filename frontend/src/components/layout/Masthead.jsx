import { NavLink, useNavigate } from 'react-router-dom'
import { Lock, Sun, Moon, LogOut } from 'lucide-react'
import Search from '@/components/Search'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/components/theme-provider'
import { cn } from '@/lib/utils'

const nav_items = [
  { to: '/dashboard', label: 'Home' },
  { to: '/notes', label: 'Notes' },
  { to: '/tasks', label: 'Tasks' },
  { to: '/calendar', label: 'Calendar' },
  { to: '/settings', label: 'Settings' },
]

export default function Masthead()
{
  const { user, logout } = useAuth()
  const { theme, setTheme } = useTheme()
  const navigate = useNavigate()

  // The API serialises snake_case (first_name); tolerate both shapes.
  const display_name = user?.first_name || user?.firstName || ''
  const initial = (display_name.trim()[0] || user?.email?.[0] || 'P').toUpperCase()

  const handle_logout = async () =>
  {
    await logout()
    navigate('/login')
  }

  return (
    <div className="sticky top-0 z-20 border-b border-line backdrop-blur-xl"
      style={{ background: 'color-mix(in srgb, var(--paper) 82%, transparent)' }}>
    <header className="flex items-center gap-4 px-4 py-3 md:gap-6 md:px-8">

      <NavLink to="/dashboard" className="flex flex-none items-center gap-2.5">
        <span className="grid h-7 w-7 place-items-center rounded-lg bg-ink text-[15px] font-bold text-paper">P</span>
        <span className="hidden text-[17px] font-bold tracking-tight sm:inline">Pryvora</span>
      </NavLink>

      <nav className="hidden items-center gap-0.5 lg:flex">
        {nav_items.map(item => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) => cn(
              'rounded-[9px] px-3 py-1.5 text-[13px] transition-colors',
              isActive ? 'font-bold text-ink' : 'font-semibold text-dim hover:text-ink'
            )}
            style={({ isActive }) => (
              isActive ? { background: 'color-mix(in srgb, var(--accent) 14%, transparent)' } : undefined
            )}
          >
            {item.label}
          </NavLink>
        ))}
      </nav>

      <div className="ml-auto min-w-0 flex-1 md:max-w-[280px]">
        <Search/>
      </div>

      <span className="hidden flex-none items-center gap-1.5 text-[11px] font-semibold text-dim xl:inline-flex">
        <Lock className="h-3 w-3"/>
        Local
      </span>

      <button
        type="button"
        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}
        className="grid h-8 w-8 flex-none place-items-center rounded-[9px] text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
      >
        {theme === 'dark' ? <Sun className="h-4 w-4"/> : <Moon className="h-4 w-4"/>}
      </button>

      <button
        type="button"
        onClick={handle_logout}
        aria-label="Log out"
        className="grid h-8 w-8 flex-none place-items-center rounded-[9px] text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
      >
        <LogOut className="h-4 w-4"/>
      </button>

      <span
        title={user?.email}
        className="grid h-[34px] w-[34px] flex-none place-items-center rounded-full bg-accent text-[13px] font-bold text-on-accent"
      >
        {initial}
      </span>
    </header>

    {/* Below lg the nav pills drop to their own scrollable row. */}
    <nav className="flex items-center gap-0.5 overflow-x-auto px-4 pb-2 lg:hidden">
      {nav_items.map(item => (
        <NavLink
          key={item.to}
          to={item.to}
          className={({ isActive }) => cn(
            'flex-none rounded-[9px] px-3 py-1.5 text-[13px] transition-colors',
            isActive ? 'font-bold text-ink' : 'font-semibold text-dim hover:text-ink'
          )}
          style={({ isActive }) => (
            isActive ? { background: 'color-mix(in srgb, var(--accent) 14%, transparent)' } : undefined
          )}
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
    </div>
  )
}
