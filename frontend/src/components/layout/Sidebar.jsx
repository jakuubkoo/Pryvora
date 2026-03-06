import { Link, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuth } from '@/contexts/AuthContext'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'

const nav_items = [
  { name: 'Dashboard', path: '/dashboard' },
  { name: 'Notes', path: '/notes' },
  { name: 'Tasks', path: '/tasks' },
  { name: 'Calendar', path: '/calendar' },
  { name: 'Settings', path: '/settings' },
]

export default function Sidebar()
{
  const location = useLocation()
  const { user, logout } = useAuth()

  const handle_logout = async () =>
  {
    await logout()
  }

  return (
    <div className="w-64 h-screen bg-[#0f0f0f] border-r border-[#1a1a1a] flex flex-col">
      <div className="p-6">
        <h1 className="text-xl font-semibold tracking-tight text-[#e5e5e5]">Pryvora</h1>
      </div>

      <Separator className="bg-[#1a1a1a]"/>

      <nav className="flex-1 p-4 space-y-1">
        {nav_items.map((item) =>
        {
          const is_active = location.pathname === item.path
          return (
            <Link
              key={item.path}
              to={item.path}
              className="block"
            >
              <motion.div
                className={`
                  px-3 py-2 rounded-md text-sm font-medium
                  ${is_active
                    ? 'bg-[#1a1a1a] text-[#e5e5e5]'
                    : 'text-[#888888]'
                  }
                `}
                whileHover={{
                  backgroundColor: is_active ? 'rgba(26, 26, 26, 1)' : 'rgba(21, 21, 21, 1)',
                  color: '#e5e5e5',
                  transition: { duration: 0.15 },
                }}
                whileTap={{
                  scale: 0.98,
                  transition: { duration: 0.1 },
                }}
              >
                {item.name}
              </motion.div>
            </Link>
          )
        })}
      </nav>

      <div className="p-4 space-y-3">
        <Separator className="bg-[#1a1a1a]"/>
        <div className="px-3 py-2">
          <p className="text-xs text-[#666666]">Signed in as</p>
          <p className="text-sm text-[#e5e5e5] truncate mt-1">{user?.email || 'user@example.com'}</p>
        </div>
        <Button
          onClick={handle_logout}
          variant="ghost"
          className="w-full justify-start text-[#888888] hover:text-[#e5e5e5] hover:bg-[#151515] transition-colors duration-150"
        >
          Logout
        </Button>
      </div>
    </div>
  )
}

