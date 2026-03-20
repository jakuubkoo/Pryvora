import { motion } from 'framer-motion'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { useAuth } from '@/contexts/AuthContext'
import Search from '@/components/Search'

const title_variants = {
  hidden: { opacity: 0, x: -8 },
  visible: {
    opacity: 1,
    x: 0,
    transition: {
      duration: 0.25,
      ease: [0.22, 1, 0.36, 1],
      delay: 0.05,
    },
  },
}

const avatar_variants = {
  hidden: { opacity: 0, scale: 0.9 },
  visible: {
    opacity: 1,
    scale: 1,
    transition: {
      duration: 0.25,
      ease: [0.22, 1, 0.36, 1],
      delay: 0.1,
    },
  },
}

export default function Topbar({ title = 'Dashboard' })
{
  const { user } = useAuth()

  const get_initials = (email) =>
  {
    if (!email)
    {
      return 'U'
    }
    return email.charAt(0).toUpperCase()
  }

  return (
    <div className="h-16 border-b border-[#1a1a1a] bg-[#0f0f0f] flex items-center justify-between px-6 gap-6">
      <motion.h2
        className="text-lg font-medium text-[#e5e5e5] shrink-0"
        variants={title_variants}
        initial="hidden"
        animate="visible"
      >
        {title}
      </motion.h2>
      
      {/* Centered Search */}
      <div className="flex-1 flex justify-center">
        <Search />
      </div>
      
      <motion.div
        variants={avatar_variants}
        initial="hidden"
        animate="visible"
        className="shrink-0"
      >
        <Avatar className="h-8 w-8 bg-[#1a1a1a] border border-[#2a2a2a]">
          <AvatarFallback className="bg-[#1a1a1a] text-[#888888] text-xs">
            {get_initials(user?.email)}
          </AvatarFallback>
        </Avatar>
      </motion.div>
    </div>
  )
}

