import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Search as SearchIcon, FileText, CheckSquare, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { useAuth } from '@/contexts/AuthContext'
import { useNavigate } from 'react-router-dom'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

const result_variants = {
  hidden: { opacity: 0, y: 10, scale: 0.98 },
  visible: {
    opacity: 1,
    y: 0,
    scale: 1,
    transition: {
      duration: 0.2,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: 0,
    y: -10,
    scale: 0.98,
    transition: {
      duration: 0.15,
    },
  },
}

const icon_variants = {
  hidden: { scale: 0, rotate: -45 },
  visible: {
    scale: 1,
    rotate: 0,
    transition: {
      duration: 0.3,
      ease: [0.34, 1.56, 0.64, 1],
    },
  },
}

export default function Search()
{
  const { api_request } = useAuth()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [results, setResults] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [isOpen, setIsOpen] = useState(false)
  const [debouncedQuery, setDebouncedQuery] = useState('')

  // Debounce search query
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedQuery(query)
    }, 300)

    return () => clearTimeout(timer)
  }, [query])

  // Fetch search results
  useEffect(() => {
    const fetchResults = async () => {
      if (!debouncedQuery || debouncedQuery.length < 2) {
        setResults([])
        return
      }

      setIsLoading(true)
      try {
        const response = await api_request(
          `${API_BASE_URL}/api/search?query=${encodeURIComponent(debouncedQuery)}`
        )

        if (response.ok) {
          const data = await response.json()
          setResults(data)
          setIsOpen(true)
        } else {
          setResults([])
        }
      } catch (error) {
        console.error('Search failed:', error)
        setResults([])
      } finally {
        setIsLoading(false)
      }
    }

    fetchResults()
  }, [debouncedQuery, api_request])

  const getIcon = (type) => {
    return type === 'note' ? FileText : CheckSquare
  }

  const getTypeColor = (type) => {
    return type === 'note' 
      ? 'text-[#8b5cf6] bg-[#8b5cf6]/10 border-[#8b5cf6]/20'
      : 'text-[#06b6d4] bg-[#06b6d4]/10 border-[#06b6d4]/20'
  }

  const clearSearch = () => {
    setQuery('')
    setResults([])
    setIsOpen(false)
  }

  const handleResultClick = (result) => {
    clearSearch()
    navigate(`/${result.type}s`)
  }

  return (
    <div className="relative w-full max-w-2xl">
      {/* Search Input */}
      <div className="relative">
        <motion.div
          className="absolute left-4 top-1/2 -translate-y-1/2 text-[#666]"
          animate={isLoading ? { rotate: 180 } : { rotate: 0 }}
          transition={{ duration: 0.3 }}
        >
          <SearchIcon className="h-5 w-5" />
        </motion.div>
        
        <Input
          type="text"
          placeholder="Search notes and tasks..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="pl-12 pr-12 h-12 bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666] focus-visible:border-[#8b5cf6]/50 focus-visible:ring-[#8b5cf6]/20"
        />

        <AnimatePresence>
          {query && (
            <motion.button
              initial={{ scale: 0, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0, opacity: 0 }}
              whileHover={{ scale: 1.1 }}
              whileTap={{ scale: 0.9 }}
              className="absolute right-4 top-1/2 -translate-y-1/2 text-[#666] hover:text-[#e5e5e5] transition-colors"
              onClick={clearSearch}
            >
              <X className="h-4 w-4" />
            </motion.button>
          )}
        </AnimatePresence>
      </div>

      {/* Search Results Dropdown */}
      <AnimatePresence>
        {isOpen && results.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: -10, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -10, scale: 0.98 }}
            transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
            className="absolute top-full left-0 right-0 mt-3 z-50"
          >
            <Card className="bg-[#1a1a1a]/95 border-[#2a2a2a] backdrop-blur-xl shadow-2xl shadow-black/50 overflow-hidden">
              <div className="max-h-[400px] overflow-y-auto custom-scrollbar">
                {results.map((result, index) => {
                  const Icon = getIcon(result.type)
                  const colorClass = getTypeColor(result.type)

                  return (
                    <motion.div
                      key={result.id}
                      variants={result_variants}
                      initial="hidden"
                      animate="visible"
                      exit="exit"
                      transition={{ delay: index * 0.03 }}
                      onClick={() => handleResultClick(result)}
                      className="group flex items-start gap-4 p-4 hover:bg-[#2a2a2a]/50 transition-colors cursor-pointer border-b border-[#2a2a2a]/50 last:border-b-0"
                    >
                      {/* Icon */}
                      <motion.div
                        variants={icon_variants}
                        className={`flex items-center justify-center w-10 h-10 rounded-lg border ${colorClass}`}
                      >
                        <Icon className="h-5 w-5" />
                      </motion.div>

                      {/* Content */}
                      <div className="flex-1 min-w-0">
                        <h4 className="text-[#e5e5e5] font-medium truncate group-hover:text-[#fff] transition-colors">
                          {result.title}
                        </h4>
                        <div className="flex items-center gap-2 mt-1">
                          <span className={`text-xs px-2 py-0.5 rounded-full border ${colorClass}`}>
                            {result.type === 'note' ? 'Note' : 'Task'}
                          </span>
                        </div>
                      </div>

                      {/* Arrow indicator */}
                      <motion.div
                        initial={{ x: -4, opacity: 0 }}
                        animate={{ x: 0, opacity: 1 }}
                        transition={{ delay: 0.2 }}
                        className="opacity-0 group-hover:opacity-100 transition-opacity"
                      >
                        <svg
                          className="w-5 h-5 text-[#666]"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.5}
                            d="M9 5l7 7-7 7"
                          />
                        </svg>
                      </motion.div>
                    </motion.div>
                  )
                })}
              </div>

              {/* Results count footer */}
              <div className="px-4 py-3 border-t border-[#2a2a2a] bg-[#151515]/50">
                <p className="text-xs text-[#666]">
                  {results.length} {results.length === 1 ? 'result' : 'results'} found
                </p>
              </div>
            </Card>
          </motion.div>
        )}
      </AnimatePresence>

      {/* No Results State */}
      <AnimatePresence>
        {isOpen && query && debouncedQuery && results.length === 0 && !isLoading && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="absolute top-full left-0 right-0 mt-3 z-50"
          >
            <Card className="bg-[#1a1a1a]/95 border-[#2a2a2a] backdrop-blur-xl shadow-2xl shadow-black/50">
              <div className="flex flex-col items-center justify-center py-12 px-4">
                <motion.div
                  initial={{ scale: 0.8, opacity: 0 }}
                  animate={{ scale: 1, opacity: 1 }}
                  transition={{ type: 'spring', duration: 0.5 }}
                  className="w-16 h-16 rounded-full bg-[#2a2a2a] flex items-center justify-center mb-4"
                >
                  <SearchIcon className="h-8 w-8 text-[#444]" />
                </motion.div>
                <p className="text-[#888] font-medium">No results found</p>
                <p className="text-[#666] text-sm mt-1">
                  Try searching for something else
                </p>
              </div>
            </Card>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
