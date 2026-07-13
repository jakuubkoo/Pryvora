import { useState, useEffect, useRef } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Search as SearchIcon, FileText, CheckSquare, X, ChevronRight } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { useNavigate } from 'react-router-dom'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

export default function Search()
{
  const { api_request } = useAuth()
  const navigate = useNavigate()
  const input_ref = useRef(null)
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

  // ⌘K / Ctrl+K focuses the field — the masthead advertises the shortcut, so it must work.
  useEffect(() => {
    const on_key = (e) =>
    {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')
      {
        e.preventDefault()
        input_ref.current?.focus()
      }
      if (e.key === 'Escape')
      {
        setIsOpen(false)
        input_ref.current?.blur()
      }
    }

    window.addEventListener('keydown', on_key)
    return () => window.removeEventListener('keydown', on_key)
  }, [])

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
    <div className="relative w-full">
      <div className="flex items-center gap-2.5 rounded-[11px] border border-line bg-panel px-3 py-2">
        <SearchIcon className={`h-[15px] w-[15px] flex-none transition-colors ${isLoading ? 'text-accent' : 'text-faint'}`}/>

        <input
          ref={input_ref}
          type="text"
          placeholder="Search everything…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setIsOpen(true)}
          className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-ink outline-none placeholder:text-faint"
        />

        {query ? (
          <button
            type="button"
            onClick={clearSearch}
            aria-label="Clear search"
            className="flex-none text-faint transition-colors hover:text-ink"
          >
            <X className="h-3.5 w-3.5"/>
          </button>
        ) : (
          <span className="hidden flex-none rounded-[5px] border border-line px-1.5 py-px text-[10px] font-bold text-faint sm:inline">
            ⌘K
          </span>
        )}
      </div>

      <AnimatePresence>
        {isOpen && results.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.16, ease: [0.22, 1, 0.36, 1] }}
            className="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-[14px] border border-line bg-panel"
          >
            <div className="custom-scrollbar max-h-[380px] overflow-y-auto">
              {results.map(result => {
                const Icon = result.type === 'note' ? FileText : CheckSquare
                const tint = result.type === 'note' ? 'var(--clay)' : 'var(--accent)'

                return (
                  <button
                    type="button"
                    key={`${result.type}-${result.id}`}
                    onClick={() => handleResultClick(result)}
                    className="group flex w-full items-center gap-3 border-b border-line2 px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-panel-sunk"
                  >
                    <span
                      className="grid h-8 w-8 flex-none place-items-center rounded-[9px]"
                      style={{ background: `color-mix(in srgb, ${tint} 14%, transparent)`, color: tint }}
                    >
                      <Icon className="h-4 w-4"/>
                    </span>

                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-[13px] font-semibold text-ink">{result.title}</span>
                      <span className="text-[11px] font-semibold text-faint">
                        {result.type === 'note' ? 'Note' : 'Task'}
                      </span>
                    </span>

                    <ChevronRight className="h-4 w-4 flex-none text-faint opacity-0 transition-opacity group-hover:opacity-100"/>
                  </button>
                )
              })}
            </div>

            <div className="border-t border-line bg-panel-sunk px-3 py-2">
              <p className="num text-[11px] text-faint">
                {results.length} {results.length === 1 ? 'result' : 'results'}
              </p>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {isOpen && debouncedQuery.length >= 2 && results.length === 0 && !isLoading && (
          <motion.div
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            className="absolute left-0 right-0 top-full z-50 mt-2 rounded-[14px] border border-line bg-panel px-4 py-6 text-center"
          >
            <p className="text-[13px] font-semibold text-dim">No results</p>
            <p className="mt-1 text-[11.5px] text-faint">
              Search only reads titles — note bodies stay encrypted.
            </p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
