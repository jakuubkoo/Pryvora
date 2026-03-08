import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
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
      staggerChildren: should_reduce ? 0 : 0.05,
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

export default function Notes()
{
  const { api_request } = useAuth()
  const should_reduce_motion = use_reduced_motion()

  const [notes, set_notes] = useState([])
  const [loading, set_loading] = useState(true)
  const [error, set_error] = useState('')
  const [success, set_success] = useState('')

  const [show_create_modal, set_show_create_modal] = useState(false)
  const [show_edit_modal, set_show_edit_modal] = useState(false)
  const [show_delete_modal, set_show_delete_modal] = useState(false)

  const [new_note_title, set_new_note_title] = useState('')
  const [new_note_content, set_new_note_content] = useState('')
  const [creating, set_creating] = useState(false)

  const [edit_note, set_edit_note] = useState(null)
  const [edit_note_title, set_edit_note_title] = useState('')
  const [edit_note_content, set_edit_note_content] = useState('')
  const [updating, set_updating] = useState(false)

  const [delete_note, set_delete_note] = useState(null)
  const [deleting, set_deleting] = useState(false)

  const page_variants = get_page_variants(should_reduce_motion)
  const container_variants = get_container_variants(should_reduce_motion)
  const card_variants = get_card_variants(should_reduce_motion)

  useEffect(() =>
  {
    fetch_notes()
  }, [])

  const fetch_notes = async () =>
  {
    set_loading(true)
    set_error('')

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/notes/`)

      if (!response.ok)
      {
        throw new Error('Failed to fetch notes')
      }

      const data = await response.json()
      set_notes(data.notes || [])
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

  const handle_create_note = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_success('')
    set_creating(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/notes/`, {
        method: 'POST',
        body: JSON.stringify({
          title: new_note_title,
          content: new_note_content,
        }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to create note')
      }

      set_success('Note created successfully!')
      set_new_note_title('')
      set_new_note_content('')
      set_show_create_modal(false)
      await fetch_notes()

      setTimeout(() => set_success(''), 3000)
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_creating(false)
    }
  }

  const handle_edit_note = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_success('')
    set_updating(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/notes/${edit_note.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          title: edit_note_title,
          content: edit_note_content,
        }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to update note')
      }

      set_success('Note updated successfully!')
      set_show_edit_modal(false)
      set_edit_note(null)
      await fetch_notes()

      setTimeout(() => set_success(''), 3000)
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_updating(false)
    }
  }


  const handle_delete_note = async () =>
  {
    set_error('')
    set_success('')
    set_deleting(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/notes/${delete_note.id}`, {
        method: 'DELETE',
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to delete note')
      }

      set_success('Note deleted successfully!')
      set_show_delete_modal(false)
      set_delete_note(null)
      await fetch_notes()

      setTimeout(() => set_success(''), 3000)
    }
    catch (err)
    {
      set_error(err.message)
    }
    finally
    {
      set_deleting(false)
    }
  }

  const open_edit_modal = (note) =>
  {
    set_edit_note(note)
    set_edit_note_title(note.title)
    set_edit_note_content(note.content)
    set_show_edit_modal(true)
  }

  const open_delete_modal = (note) =>
  {
    set_delete_note(note)
    set_show_delete_modal(true)
  }

  const format_date = (date_string) =>
  {
    const date = new Date(date_string)
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  return (
    <AppLayout title="Notes">
      <motion.div
        className="p-6 space-y-6"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        {/* Header with Create Button */}
        <motion.div
          className="flex items-center justify-between"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
        >
          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.4, delay: 0.1, ease: [0.22, 1, 0.36, 1] }}
          >
            <h1 className="text-2xl font-semibold text-[#e5e5e5] mb-1">
              Your Notes
            </h1>
            <p className="text-sm text-[#888888]">
              Capture your thoughts and ideas securely
            </p>
          </motion.div>

          <Dialog open={show_create_modal} onOpenChange={set_show_create_modal}>
            <DialogTrigger asChild>
              <motion.div
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.3, delay: 0.2, ease: [0.22, 1, 0.36, 1] }}
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d0d0d0] transition-colors duration-150">
                  + New Note
                </Button>
              </motion.div>
            </DialogTrigger>
            <DialogContent className="bg-[#0f0f0f] border-[#1a1a1a] text-[#e5e5e5]">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
              >
                <DialogHeader>
                  <DialogTitle className="text-[#e5e5e5]">Create New Note</DialogTitle>
                  <DialogDescription className="text-[#888888]">
                    Add a new note to your collection
                  </DialogDescription>
                </DialogHeader>

                <form onSubmit={handle_create_note} className="space-y-4">
                {error && (
                  <Alert variant="destructive">
                    <AlertDescription>{error}</AlertDescription>
                  </Alert>
                )}

                <div className="space-y-2">
                  <Label htmlFor="title" className="text-[#e5e5e5]">Title</Label>
                  <Input
                    id="title"
                    placeholder="Enter note title"
                    value={new_note_title}
                    onChange={(e) => set_new_note_title(e.target.value)}
                    disabled={creating}
                    className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="content" className="text-[#e5e5e5]">Content</Label>
                  <textarea
                    id="content"
                    placeholder="Write your note here..."
                    value={new_note_content}
                    onChange={(e) => set_new_note_content(e.target.value)}
                    disabled={creating}
                    className="w-full min-h-[200px] px-3 py-2 bg-[#1a1a1a] border border-[#2a2a2a] rounded-md text-[#e5e5e5] placeholder:text-[#666666] focus:outline-none focus:ring-2 focus:ring-[#e5e5e5] focus:ring-offset-2 focus:ring-offset-[#0f0f0f] resize-none"
                    required
                  />
                </div>

                  <div className="flex gap-3 justify-end">
                    <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
                      <Button
                        type="button"
                        variant="ghost"
                        onClick={() => set_show_create_modal(false)}
                        disabled={creating}
                        className="text-[#888888] hover:text-[#e5e5e5] hover:bg-[#151515]"
                      >
                        Cancel
                      </Button>
                    </motion.div>
                    <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
                      <Button
                        type="submit"
                        disabled={creating}
                        className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d0d0d0]"
                      >
                        {creating ? 'Creating...' : 'Create Note'}
                      </Button>
                    </motion.div>
                  </div>
                </form>
              </motion.div>
            </DialogContent>
          </Dialog>
        </motion.div>

        {/* Success/Error Messages */}
        <AnimatePresence>
          {success && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
            >
              <Alert className="bg-green-900/20 border-green-900/50 text-green-400">
                <AlertDescription>{success}</AlertDescription>
              </Alert>
            </motion.div>
          )}
          {error && !show_create_modal && !show_edit_modal && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
            >
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Loading State */}
        {loading && (
          <motion.div
            className="flex items-center justify-center py-12"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
          >
            <motion.div
              className="text-[#888888]"
              animate={{
                opacity: [0.5, 1, 0.5],
              }}
              transition={{
                duration: 1.5,
                repeat: Infinity,
                ease: "easeInOut"
              }}
            >
              Loading notes...
            </motion.div>
          </motion.div>
        )}

        {/* Empty State */}
        {!loading && notes.length === 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
          >
            <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
              <CardContent className="flex flex-col items-center justify-center py-12">
                <motion.div
                  className="text-6xl mb-4"
                  initial={{ scale: 0, rotate: -180 }}
                  animate={{ scale: 1, rotate: 0 }}
                  transition={{
                    duration: 0.6,
                    delay: 0.2,
                    type: "spring",
                    stiffness: 200,
                    damping: 15
                  }}
                >
                  📝
                </motion.div>
                <motion.div
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.4, delay: 0.4 }}
                >
                  <CardTitle className="text-[#e5e5e5] mb-2">No notes yet</CardTitle>
                </motion.div>
                <motion.div
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.4, delay: 0.5 }}
                >
                  <CardDescription className="text-[#888888] mb-6">
                    Create your first note to get started
                  </CardDescription>
                </motion.div>
                <motion.div
                  initial={{ opacity: 0, scale: 0.8 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ duration: 0.4, delay: 0.6 }}
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    onClick={() => set_show_create_modal(true)}
                    className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d0d0d0]"
                  >
                    + Create Note
                  </Button>
                </motion.div>
              </CardContent>
            </Card>
          </motion.div>
        )}

        {/* Notes Grid */}
        {!loading && notes.length > 0 && (
          <motion.div
            className="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            variants={container_variants}
            initial="hidden"
            animate="visible"
          >
            {notes.map((note, index) => (
              <motion.div
                key={note.id}
                variants={card_variants}
                whileHover={{
                  y: -4,
                  transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] }
                }}
                layout
              >
                <Card className="border-[#1a1a1a] bg-[#0f0f0f] transition-all duration-200 hover:shadow-lg hover:shadow-black/20 hover:border-[#2a2a2a] group overflow-hidden relative">
                  <motion.div
                    className="absolute inset-0 bg-gradient-to-br from-[#1a1a1a]/0 to-[#1a1a1a]/0 group-hover:from-[#1a1a1a]/30 group-hover:to-transparent transition-all duration-300"
                    initial={{ opacity: 0 }}
                    whileHover={{ opacity: 1 }}
                  />
                  <motion.div
                    className="relative z-10"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.3, delay: index * 0.05 }}
                  >
                    <CardHeader>
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                          <CardTitle className="text-[#e5e5e5] text-lg truncate">
                            {note.title}
                          </CardTitle>
                          <CardDescription className="text-[#666666] text-xs mt-1">
                            {format_date(note.updated_at || note.created_at)}
                          </CardDescription>
                        </div>
                        <motion.div
                          className="flex gap-1"
                          initial={{ opacity: 0, x: 10 }}
                          animate={{ opacity: 0, x: 0 }}
                          whileHover={{ opacity: 1 }}
                          transition={{ duration: 0.2 }}
                        >
                          <motion.div whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => open_edit_modal(note)}
                              className="h-8 w-8 p-0 text-[#888888] hover:text-[#e5e5e5] hover:bg-[#151515]"
                            >
                              ✏️
                            </Button>
                          </motion.div>
                          <motion.div whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => open_delete_modal(note)}
                              className="h-8 w-8 p-0 text-[#888888] hover:text-red-400 hover:bg-[#151515]"
                            >
                              🗑️
                            </Button>
                          </motion.div>
                        </motion.div>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <p className="text-sm text-[#888888] line-clamp-4 whitespace-pre-wrap">
                        {note.content}
                      </p>
                    </CardContent>
                  </motion.div>
                </Card>
              </motion.div>
            ))}
          </motion.div>
        )}

        {/* Edit Note Modal */}
        <AnimatePresence>
          {show_edit_modal && (
            <Dialog open={show_edit_modal} onOpenChange={set_show_edit_modal}>
              <DialogContent className="bg-[#0f0f0f] border-[#1a1a1a] text-[#e5e5e5]">
                <motion.div
                  initial={{ opacity: 0, scale: 0.95 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.95 }}
                  transition={{ duration: 0.2 }}
                >
                  <DialogHeader>
                    <DialogTitle className="text-[#e5e5e5]">Edit Note</DialogTitle>
                    <DialogDescription className="text-[#888888]">
                      Make changes to your note
                    </DialogDescription>
                  </DialogHeader>

                  <form onSubmit={handle_edit_note} className="space-y-4">
              {error && (
                <Alert variant="destructive">
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-2">
                <Label htmlFor="edit-title" className="text-[#e5e5e5]">Title</Label>
                <Input
                  id="edit-title"
                  placeholder="Enter note title"
                  value={edit_note_title}
                  onChange={(e) => set_edit_note_title(e.target.value)}
                  disabled={updating}
                  className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="edit-content" className="text-[#e5e5e5]">Content</Label>
                <textarea
                  id="edit-content"
                  placeholder="Write your note here..."
                  value={edit_note_content}
                  onChange={(e) => set_edit_note_content(e.target.value)}
                  disabled={updating}
                  className="w-full min-h-[200px] px-3 py-2 bg-[#1a1a1a] border border-[#2a2a2a] rounded-md text-[#e5e5e5] placeholder:text-[#666666] focus:outline-none focus:ring-2 focus:ring-[#e5e5e5] focus:ring-offset-2 focus:ring-offset-[#0f0f0f] resize-none"
                  required
                />
              </div>

                  <div className="flex gap-3 justify-end">
                      <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() => set_show_edit_modal(false)}
                          disabled={updating}
                          className="text-[#888888] hover:text-[#e5e5e5] hover:bg-[#151515]"
                        >
                          Cancel
                        </Button>
                      </motion.div>
                      <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
                        <Button
                          type="submit"
                          disabled={updating}
                          className="bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d0d0d0]"
                        >
                          {updating ? 'Updating...' : 'Update Note'}
                        </Button>
                      </motion.div>
                    </div>
                  </form>
                </motion.div>
              </DialogContent>
            </Dialog>
          )}
        </AnimatePresence>

        {/* Delete Confirmation Modal */}
        <AnimatePresence>
          {show_delete_modal && (
            <Dialog open={show_delete_modal} onOpenChange={set_show_delete_modal}>
              <DialogContent className="bg-[#0f0f0f] border-[#1a1a1a] text-[#e5e5e5]">
                <motion.div
                  initial={{ opacity: 0, scale: 0.95 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.95 }}
                  transition={{ duration: 0.2 }}
                >
                  <DialogHeader>
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.3, delay: 0.1 }}
                    >
                      <DialogTitle className="text-[#e5e5e5]">Delete Note</DialogTitle>
                    </motion.div>
                    <motion.div
                      initial={{ opacity: 0, y: -10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.3, delay: 0.15 }}
                    >
                      <DialogDescription className="text-[#888888]">
                        Are you sure you want to delete this note? This action cannot be undone.
                      </DialogDescription>
                    </motion.div>
                  </DialogHeader>

                  {delete_note && (
                    <motion.div
                      className="py-4"
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.3, delay: 0.2 }}
                    >
                      <p className="text-sm text-[#e5e5e5] font-medium mb-2">
                        {delete_note.title}
                      </p>
                      <p className="text-sm text-[#666666] line-clamp-3">
                        {delete_note.content}
                      </p>
                    </motion.div>
                  )}

                  <motion.div
                    className="flex gap-3 justify-end"
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, delay: 0.25 }}
                  >
                    <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }}>
                      <Button
                        type="button"
                        variant="ghost"
                        onClick={() => set_show_delete_modal(false)}
                        disabled={deleting}
                        className="text-[#888888] hover:text-[#e5e5e5] hover:bg-[#151515]"
                      >
                        Cancel
                      </Button>
                    </motion.div>
                    <motion.div
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.98 }}
                      animate={deleting ? {} : {
                        boxShadow: [
                          "0 0 0 0 rgba(220, 38, 38, 0)",
                          "0 0 0 4px rgba(220, 38, 38, 0.1)",
                          "0 0 0 0 rgba(220, 38, 38, 0)"
                        ]
                      }}
                      transition={{
                        duration: 2,
                        repeat: Infinity,
                        ease: "easeInOut"
                      }}
                    >
                      <Button
                        onClick={handle_delete_note}
                        disabled={deleting}
                        className="bg-red-600 text-white hover:bg-red-700"
                      >
                        {deleting ? 'Deleting...' : 'Delete Note'}
                      </Button>
                    </motion.div>
                  </motion.div>
                </motion.div>
              </DialogContent>
            </Dialog>
          )}
        </AnimatePresence>
      </motion.div>
    </AppLayout>
  )
}

