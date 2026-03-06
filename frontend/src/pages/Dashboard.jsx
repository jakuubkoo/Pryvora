import { motion } from 'framer-motion'
import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'

const get_page_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 8 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.3,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

const get_container_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: should_reduce ? 0 : 0.08,
      delayChildren: should_reduce ? 0 : 0.1,
    },
  },
})

const get_card_variants = (should_reduce) => ({
  hidden: { opacity: should_reduce ? 1 : 0, y: should_reduce ? 0 : 12 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: should_reduce ? 0 : 0.25,
      ease: [0.22, 1, 0.36, 1],
    },
  },
})

export default function Dashboard()
{
  const { user } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const page_variants = get_page_variants(should_reduce_motion)
  const container_variants = get_container_variants(should_reduce_motion)
  const card_variants = get_card_variants(should_reduce_motion)

  const get_greeting = () =>
  {
    const hour = new Date().getHours()
    if (hour < 12)
    {
      return 'Good morning'
    }
    if (hour < 18)
    {
      return 'Good afternoon'
    }
    return 'Good evening'
  }

  return (
    <AppLayout title="Dashboard">
      <motion.div
        className="p-6 space-y-6"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        <div>
          <h1 className="text-2xl font-semibold text-[#e5e5e5] mb-1">
            {get_greeting()}
          </h1>
          <p className="text-sm text-[#888888]">
            {user?.email || 'Welcome back'}
          </p>
        </div>

        <motion.div
          className="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
          variants={container_variants}
          initial="hidden"
          animate="visible"
        >
          <motion.div variants={card_variants}>
            <Card className="border-[#1a1a1a] bg-[#0f0f0f] transition-all duration-200 hover:shadow-lg hover:shadow-black/20 hover:-translate-y-0.5">
              <CardHeader>
                <CardTitle className="text-[#e5e5e5]">Welcome back</CardTitle>
                <CardDescription className="text-[#888888]">
                  Your personal operating system
                </CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-[#666666]">
                  Start organizing your life with Pryvora
                </p>
              </CardContent>
            </Card>
          </motion.div>

          <motion.div variants={card_variants}>
            <Card className="border-[#1a1a1a] bg-[#0f0f0f] transition-all duration-200 hover:shadow-lg hover:shadow-black/20 hover:-translate-y-0.5">
              <CardHeader>
                <CardTitle className="text-[#e5e5e5]">Tasks</CardTitle>
                <CardDescription className="text-[#888888]">
                  No tasks yet
                </CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-[#666666]">
                  Create your first task to get started
                </p>
              </CardContent>
            </Card>
          </motion.div>

          <motion.div variants={card_variants}>
            <Card className="border-[#1a1a1a] bg-[#0f0f0f] transition-all duration-200 hover:shadow-lg hover:shadow-black/20 hover:-translate-y-0.5">
              <CardHeader>
                <CardTitle className="text-[#e5e5e5]">Notes</CardTitle>
                <CardDescription className="text-[#888888]">
                  No notes yet
                </CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-[#666666]">
                  Capture your thoughts and ideas
                </p>
              </CardContent>
            </Card>
          </motion.div>
        </motion.div>
      </motion.div>
    </AppLayout>
  )
}

