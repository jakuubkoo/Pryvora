import { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import {
  AlertTriangle,
  Check,
  Lock,
  NotebookPen,
  Pencil,
  Plus,
  RotateCw,
  Trash2,
} from 'lucide-react'
import AppLayout from '@/components/layout/AppLayout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import TagSelector from '@/components/TagSelector'
import { useAuth } from '@/contexts/AuthContext'
import { use_reduced_motion } from '@/hooks/use-reduced-motion.js'

const ACCENT_TINT = 'color-mix(in srgb, var(--accent) 13%, transparent)'
const DOWN_TINT = 'color-mix(in srgb, var(--down) 13%, transparent)'

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

/* A tag's colour is user-chosen and can be any hex — so it never fills the
   chip. The chip stays on-palette and the colour appears only as a dot. */
function TagChip({ tag })
{
  return (
    <span className="inline-flex max-w-full items-center gap-1.5 rounded-[20px] border border-line bg-panel-sunk px-2.5 py-0.5 text-xs font-medium text-dim">
      <span
        aria-hidden="true"
        className="size-2 shrink-0 rounded-full border border-line"
        style={{ backgroundColor: tag.color || 'var(--faint)' }}
      />
      <span className="truncate">{tag.name}</span>
    </span>
  )
}

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
  const [new_note_tags, set_new_note_tags] = useState([])
  const [creating, set_creating] = useState(false)

  const [edit_note, set_edit_note] = useState(null)
  const [edit_note_title, set_edit_note_title] = useState('')
  const [edit_note_content, set_edit_note_content] = useState('')
  const [edit_note_tags, set_edit_note_tags] = useState([])
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
          tag_ids: new_note_tags.map(t => t.id),
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
      set_new_note_tags([])
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
          tag_ids: edit_note_tags.map(t => t.id),
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
    set_edit_note_tags(note.tags || [])
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

  const show_empty_state = !loading && !error && notes.length === 0
  const show_error_state = !loading && !!error && notes.length === 0

  return (
    <AppLayout>
      <motion.div
        className="space-y-7"
        initial="hidden"
        animate="visible"
        variants={page_variants}
      >
        {/* Page header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="text-[29px] font-bold leading-tight tracking-[-0.025em] text-ink">
              Your Notes
            </h1>
            <p className="mt-1 text-sm text-dim">
              Capture your thoughts and ideas securely
            </p>
          </div>

          <Button
            onClick={() => set_show_create_modal(true)}
            className="h-10 shrink-0 rounded-[11px] px-4"
          >
            <Plus className="size-4"/>
            New Note
          </Button>
        </div>

        {/* Inline toasts — auto-dismiss after 3s */}
        <AnimatePresence>
          {success && (
            <motion.div
              key="toast-success"
              role="status"
              initial={{ opacity: should_reduce_motion ? 1 : 0, y: should_reduce_motion ? 0 : -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: should_reduce_motion ? 0 : -8 }}
              transition={{ duration: should_reduce_motion ? 0 : 0.2 }}
              className="flex items-center gap-2.5 rounded-[11px] border border-line px-3.5 py-2.5 text-sm text-ink"
              style={{ background: ACCENT_TINT }}
            >
              <Check className="size-4 shrink-0 text-accent"/>
              <span>{success}</span>
            </motion.div>
          )}

          {error && !show_create_modal && !show_edit_modal && !show_error_state && (
            <motion.div
              key="toast-error"
              role="alert"
              initial={{ opacity: should_reduce_motion ? 1 : 0, y: should_reduce_motion ? 0 : -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: should_reduce_motion ? 0 : -8 }}
              transition={{ duration: should_reduce_motion ? 0 : 0.2 }}
              className="flex items-center gap-2.5 rounded-[11px] border border-line px-3.5 py-2.5 text-sm text-ink"
              style={{ background: DOWN_TINT }}
            >
              <AlertTriangle className="size-4 shrink-0 text-down"/>
              <span>{error}</span>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Meta strip */}
        {!loading && notes.length > 0 && (
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line2 pb-3">
            <span className="eyebrow num">
              {notes.length} {notes.length === 1 ? 'Note' : 'Notes'}
            </span>
            <span className="flex items-center gap-1.5 text-xs text-faint">
              <Lock className="size-3"/>
              Bodies encrypted at rest — search indexes titles only
            </span>
          </div>
        )}

        {/* Loading state */}
        {loading && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[0, 1, 2, 3, 4, 5].map(i => (
              <div key={i} className="panel animate-pulse p-5" aria-hidden="true">
                <div className="h-4 w-2/3 rounded-[8px] bg-panel-sunk"/>
                <div className="mt-2.5 h-3 w-1/3 rounded-[8px] bg-panel-sunk"/>
                <div className="mt-5 space-y-2">
                  <div className="h-3 w-full rounded-[8px] bg-panel-sunk"/>
                  <div className="h-3 w-11/12 rounded-[8px] bg-panel-sunk"/>
                  <div className="h-3 w-3/5 rounded-[8px] bg-panel-sunk"/>
                </div>
              </div>
            ))}
            <span className="sr-only" role="status">Loading notes...</span>
          </div>
        )}

        {/* Error state — nothing to show and the fetch failed */}
        {show_error_state && (
          <div className="panel flex flex-col items-center gap-3 px-6 py-14 text-center">
            <span
              className="flex size-12 items-center justify-center rounded-[9px] border border-line"
              style={{ background: DOWN_TINT }}
            >
              <AlertTriangle className="size-5 text-down"/>
            </span>
            <h2 className="text-base font-semibold text-ink">Couldn&apos;t load your notes</h2>
            <p className="max-w-sm text-sm text-dim">{error}</p>
            <Button
              variant="outline"
              onClick={fetch_notes}
              className="mt-1 rounded-[11px] bg-panel-sunk"
            >
              <RotateCw className="size-4"/>
              Try again
            </Button>
          </div>
        )}

        {/* Empty state — the first thing a new account sees */}
        {show_empty_state && (
          <motion.div
            className="panel flex flex-col items-center gap-3 px-6 py-16 text-center"
            initial={{ opacity: should_reduce_motion ? 1 : 0, y: should_reduce_motion ? 0 : 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: should_reduce_motion ? 0 : 0.35, ease: [0.22, 1, 0.36, 1] }}
          >
            <span
              className="flex size-12 items-center justify-center rounded-[9px] border border-line"
              style={{ background: ACCENT_TINT }}
            >
              <NotebookPen className="size-5 text-accent"/>
            </span>
            <h2 className="text-base font-semibold text-ink">Nothing written down yet</h2>
            <p className="max-w-sm text-sm text-dim">
              Your first note starts the archive. Bodies are encrypted at rest — only you can read them back.
            </p>
            <Button
              onClick={() => set_show_create_modal(true)}
              className="mt-2 h-10 rounded-[11px] px-4"
            >
              <Plus className="size-4"/>
              New Note
            </Button>
          </motion.div>
        )}

        {/* Notes grid */}
        {!loading && notes.length > 0 && (
          <motion.div
            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            variants={container_variants}
            initial="hidden"
            animate="visible"
          >
            {notes.map(note => (
              <motion.article
                key={note.id}
                variants={card_variants}
                layout={!should_reduce_motion}
                className="panel group relative flex flex-col p-5 transition-colors duration-200 hover:border-[color-mix(in_srgb,var(--accent)_35%,var(--line))]"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0 flex-1">
                    <h3 className="truncate text-[15px] font-semibold leading-snug text-ink">
                      {note.title}
                    </h3>
                    <p className="num mt-1 text-xs text-faint">
                      {format_date(note.updated_at || note.created_at)}
                    </p>
                  </div>

                  <div className="flex shrink-0 gap-1 opacity-100 transition-opacity duration-200 focus-within:opacity-100 md:opacity-0 md:group-hover:opacity-100">
                    <Button
                      size="icon-sm"
                      variant="ghost"
                      onClick={() => open_edit_modal(note)}
                      aria-label={`Edit note: ${note.title}`}
                      className="rounded-[8px] text-dim hover:bg-panel-sunk hover:text-ink"
                    >
                      <Pencil className="size-4"/>
                    </Button>
                    <Button
                      size="icon-sm"
                      variant="ghost"
                      onClick={() => open_delete_modal(note)}
                      aria-label={`Delete note: ${note.title}`}
                      className="rounded-[8px] text-dim hover:bg-panel-sunk hover:text-down"
                    >
                      <Trash2 className="size-4"/>
                    </Button>
                  </div>
                </div>

                <p className="mt-4 line-clamp-4 whitespace-pre-wrap break-words text-sm leading-relaxed text-dim">
                  {note.content}
                </p>

                {note.tags && note.tags.length > 0 && (
                  <div className="mt-4 flex flex-wrap gap-1.5 border-t border-line2 pt-3">
                    {note.tags.map(tag => (
                      <TagChip key={tag.id} tag={tag}/>
                    ))}
                  </div>
                )}
              </motion.article>
            ))}
          </motion.div>
        )}

        {/* Create Note Modal */}
        <Dialog open={show_create_modal} onOpenChange={set_show_create_modal}>
          <DialogContent className="max-h-[90vh] gap-5 overflow-y-auto rounded-[18px] border-line bg-panel p-6 text-ink shadow-none sm:max-w-[560px]">
            <DialogHeader>
              <DialogTitle className="text-lg font-semibold tracking-[-0.015em] text-ink">
                Create New Note
              </DialogTitle>
              <DialogDescription className="text-dim">
                Add a new note to your collection. The body is encrypted before it leaves this device.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handle_create_note} className="space-y-4">
              {error && (
                <div
                  role="alert"
                  className="flex items-center gap-2.5 rounded-[11px] border border-line px-3.5 py-2.5 text-sm text-ink"
                  style={{ background: DOWN_TINT }}
                >
                  <AlertTriangle className="size-4 shrink-0 text-down"/>
                  <span>{error}</span>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="title" className="eyebrow">Title</Label>
                <Input
                  id="title"
                  placeholder="Enter note title"
                  value={new_note_title}
                  onChange={(e) => set_new_note_title(e.target.value)}
                  disabled={creating}
                  className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink shadow-none placeholder:text-faint"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="content" className="eyebrow">Content</Label>
                <Textarea
                  id="content"
                  placeholder="Write your note here..."
                  value={new_note_content}
                  onChange={(e) => set_new_note_content(e.target.value)}
                  disabled={creating}
                  className="min-h-[180px] resize-none rounded-[11px] border-line bg-panel-sunk text-ink shadow-none placeholder:text-faint"
                  required
                />
              </div>

              <TagSelector
                selected_tags={new_note_tags}
                on_tags_change={set_new_note_tags}
              />

              <div className="flex justify-end gap-2 pt-1">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => set_show_create_modal(false)}
                  disabled={creating}
                  className="rounded-[11px] text-dim hover:bg-panel-sunk hover:text-ink"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={creating}
                  className="rounded-[11px]"
                >
                  {creating ? 'Creating...' : 'Create Note'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>

        {/* Edit Note Modal */}
        <Dialog open={show_edit_modal} onOpenChange={set_show_edit_modal}>
          <DialogContent className="max-h-[90vh] gap-5 overflow-y-auto rounded-[18px] border-line bg-panel p-6 text-ink shadow-none sm:max-w-[560px]">
            <DialogHeader>
              <DialogTitle className="text-lg font-semibold tracking-[-0.015em] text-ink">
                Edit Note
              </DialogTitle>
              <DialogDescription className="text-dim">
                Make changes to your note
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handle_edit_note} className="space-y-4">
              {error && (
                <div
                  role="alert"
                  className="flex items-center gap-2.5 rounded-[11px] border border-line px-3.5 py-2.5 text-sm text-ink"
                  style={{ background: DOWN_TINT }}
                >
                  <AlertTriangle className="size-4 shrink-0 text-down"/>
                  <span>{error}</span>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="edit-title" className="eyebrow">Title</Label>
                <Input
                  id="edit-title"
                  placeholder="Enter note title"
                  value={edit_note_title}
                  onChange={(e) => set_edit_note_title(e.target.value)}
                  disabled={updating}
                  className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink shadow-none placeholder:text-faint"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="edit-content" className="eyebrow">Content</Label>
                <Textarea
                  id="edit-content"
                  placeholder="Write your note here..."
                  value={edit_note_content}
                  onChange={(e) => set_edit_note_content(e.target.value)}
                  disabled={updating}
                  className="min-h-[180px] resize-none rounded-[11px] border-line bg-panel-sunk text-ink shadow-none placeholder:text-faint"
                  required
                />
              </div>

              <TagSelector
                selected_tags={edit_note_tags}
                on_tags_change={set_edit_note_tags}
              />

              <div className="flex justify-end gap-2 pt-1">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => set_show_edit_modal(false)}
                  disabled={updating}
                  className="rounded-[11px] text-dim hover:bg-panel-sunk hover:text-ink"
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={updating}
                  className="rounded-[11px]"
                >
                  {updating ? 'Updating...' : 'Update Note'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation Modal */}
        <Dialog open={show_delete_modal} onOpenChange={set_show_delete_modal}>
          <DialogContent className="gap-5 rounded-[18px] border-line bg-panel p-6 text-ink shadow-none sm:max-w-[460px]">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-lg font-semibold tracking-[-0.015em] text-ink">
                <Trash2 className="size-4 text-down"/>
                Delete Note
              </DialogTitle>
              <DialogDescription className="text-dim">
                Are you sure you want to delete this note? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>

            {delete_note && (
              <div className="rounded-[11px] border border-line bg-panel-sunk p-4">
                <p className="truncate text-sm font-semibold text-ink">
                  {delete_note.title}
                </p>
                <p className="mt-1.5 line-clamp-3 whitespace-pre-wrap break-words text-sm text-dim">
                  {delete_note.content}
                </p>
              </div>
            )}

            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="ghost"
                onClick={() => set_show_delete_modal(false)}
                disabled={deleting}
                className="rounded-[11px] text-dim hover:bg-panel-sunk hover:text-ink"
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                onClick={handle_delete_note}
                disabled={deleting}
                className="rounded-[11px]"
              >
                {deleting ? 'Deleting...' : 'Delete Note'}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </motion.div>
    </AppLayout>
  )
}
