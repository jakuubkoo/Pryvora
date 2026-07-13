import { useState, useEffect, useRef } from 'react'
import { AlertTriangle, Plus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAuth } from '@/contexts/AuthContext'

const DOWN_TINT = 'color-mix(in srgb, var(--down) 13%, transparent)'

export default function TagSelector({ selected_tags = [], on_tags_change })
{
  const { api_request } = useAuth()
  const [available_tags, set_available_tags] = useState([])
  const [show_dropdown, set_show_dropdown] = useState(false)
  const [show_create_dialog, set_show_create_dialog] = useState(false)
  const [new_tag_name, set_new_tag_name] = useState('')
  const [new_tag_color, set_new_tag_color] = useState('#3b82f6')
  const [loading, set_loading] = useState(false)
  const [error, set_error] = useState('')

  const dropdown_ref = useRef(null)

  useEffect(() =>
  {
    fetch_tags()
  }, [])

  useEffect(() =>
  {
    if (!show_dropdown)
    {
      return undefined
    }

    const handle_outside = (event) =>
    {
      if (dropdown_ref.current && !dropdown_ref.current.contains(event.target))
      {
        set_show_dropdown(false)
      }
    }

    const handle_escape = (event) =>
    {
      if (event.key === 'Escape')
      {
        set_show_dropdown(false)
      }
    }

    document.addEventListener('mousedown', handle_outside)
    document.addEventListener('keydown', handle_escape)

    return () =>
    {
      document.removeEventListener('mousedown', handle_outside)
      document.removeEventListener('keydown', handle_escape)
    }
  }, [show_dropdown])

  const fetch_tags = async () =>
  {
    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/tag/`)

      if (response.ok)
      {
        const data = await response.json()
        set_available_tags(data)
      }
    }
    catch (err)
    {
      console.error('Failed to fetch tags:', err)
    }
  }

  const handle_tag_select = (tag) =>
  {
    if (!selected_tags.find(t => t.id === tag.id))
    {
      on_tags_change([...selected_tags, tag])
    }
    set_show_dropdown(false)
  }

  const handle_tag_remove = (tag_id) =>
  {
    on_tags_change(selected_tags.filter(t => t.id !== tag_id))
  }

  const handle_create_tag = async (e) =>
  {
    e.preventDefault()
    set_error('')
    set_loading(true)

    try
    {
      const response = await api_request(`${import.meta.env.VITE_API_URL}/api/tag/`, {
        method: 'POST',
        body: JSON.stringify({
          name: new_tag_name,
          color: new_tag_color,
        }),
      })

      if (!response.ok)
      {
        const error_data = await response.json()
        throw new Error(error_data.error || 'Failed to create tag')
      }

      const new_tag = await response.json()
      set_available_tags([...available_tags, new_tag])
      on_tags_change([...selected_tags, new_tag])
      set_show_create_dialog(false)
      set_new_tag_name('')
      set_new_tag_color('#3b82f6')
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

  const unselected_tags = available_tags.filter(
    tag => !selected_tags.find(t => t.id === tag.id)
  )

  return (
    <div className="space-y-2">
      <Label className="eyebrow">Tags</Label>

      {selected_tags.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selected_tags.map(tag => (
            /* The tag colour is user-chosen and can be any hex, so it never
               fills the chip — it only ever shows as a dot. */
            <span
              key={tag.id}
              className="inline-flex max-w-full items-center gap-1.5 rounded-[20px] border border-line bg-panel-sunk py-0.5 pl-2.5 pr-1 text-xs font-medium text-dim"
            >
              <span
                aria-hidden="true"
                className="size-2 shrink-0 rounded-full border border-line"
                style={{ backgroundColor: tag.color || 'var(--faint)' }}
              />
              <span className="truncate">{tag.name}</span>
              <button
                type="button"
                onClick={() => handle_tag_remove(tag.id)}
                aria-label={`Remove tag ${tag.name}`}
                className="ml-0.5 rounded-full p-0.5 text-faint transition-colors hover:bg-panel hover:text-down"
              >
                <X className="size-3"/>
              </button>
            </span>
          ))}
        </div>
      )}

      <div className="relative" ref={dropdown_ref}>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => set_show_dropdown(!show_dropdown)}
          aria-expanded={show_dropdown}
          aria-haspopup="listbox"
          className="rounded-[11px] border-line bg-panel-sunk text-dim shadow-none hover:bg-panel-sunk hover:text-ink"
        >
          <Plus className="size-4"/>
          Add Tag
        </Button>

        {show_dropdown && (
          <div
            role="listbox"
            className="absolute z-20 mt-1.5 w-64 max-w-[calc(100vw-3rem)] overflow-hidden rounded-[11px] border border-line bg-panel"
          >
            <div className="max-h-48 overflow-y-auto p-1.5">
              {unselected_tags.length > 0 ? (
                unselected_tags.map(tag => (
                  <button
                    key={tag.id}
                    type="button"
                    role="option"
                    aria-selected="false"
                    onClick={() => handle_tag_select(tag)}
                    className="flex w-full items-center gap-2 rounded-[8px] px-2.5 py-2 text-left transition-colors hover:bg-panel-sunk"
                  >
                    <span
                      aria-hidden="true"
                      className="size-2.5 shrink-0 rounded-full border border-line"
                      style={{ backgroundColor: tag.color || 'var(--faint)' }}
                    />
                    <span className="truncate text-sm text-ink">{tag.name}</span>
                  </button>
                ))
              ) : (
                <p className="px-2.5 py-2 text-sm text-faint">No tags available</p>
              )}
            </div>

            <div className="border-t border-line2 p-1.5">
              <button
                type="button"
                onClick={() =>
                {
                  set_show_dropdown(false)
                  set_show_create_dialog(true)
                }}
                className="flex w-full items-center gap-2 rounded-[8px] px-2.5 py-2 text-left text-sm text-dim transition-colors hover:bg-panel-sunk hover:text-ink"
              >
                <Plus className="size-4"/>
                Create New Tag
              </button>
            </div>
          </div>
        )}
      </div>

      <Dialog open={show_create_dialog} onOpenChange={set_show_create_dialog}>
        <DialogContent className="gap-5 rounded-[18px] border-line bg-panel p-6 text-ink shadow-none sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="text-lg font-semibold tracking-[-0.015em] text-ink">
              Create New Tag
            </DialogTitle>
            <DialogDescription className="text-dim">
              Add a new tag with a custom color
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_create_tag} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="tag_name" className="eyebrow">
                Tag Name
              </Label>
              <Input
                id="tag_name"
                value={new_tag_name}
                onChange={(e) => set_new_tag_name(e.target.value)}
                required
                className="h-10 rounded-[11px] border-line bg-panel-sunk text-ink shadow-none placeholder:text-faint"
                placeholder="Enter tag name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="tag_color" className="eyebrow">
                Tag Color
              </Label>
              <div className="flex items-center gap-3">
                <input
                  id="tag_color"
                  type="color"
                  value={new_tag_color}
                  onChange={(e) => set_new_tag_color(e.target.value)}
                  className="h-10 w-16 cursor-pointer rounded-[11px] border border-line bg-panel-sunk p-1"
                />
                {/* Preview the chip exactly as it renders on a note — the colour
                    stays a dot, so any hex the user picks remains legible. */}
                <span className="inline-flex max-w-full items-center gap-1.5 rounded-[20px] border border-line bg-panel-sunk px-2.5 py-0.5 text-xs font-medium text-dim">
                  <span
                    aria-hidden="true"
                    className="size-2 shrink-0 rounded-full border border-line"
                    style={{ backgroundColor: new_tag_color }}
                  />
                  <span className="truncate">{new_tag_name || 'Preview'}</span>
                </span>
                <span className="num ml-auto text-xs uppercase text-faint">{new_tag_color}</span>
              </div>
            </div>

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

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={() => set_show_create_dialog(false)}
                variant="ghost"
                className="flex-1 rounded-[11px] text-dim hover:bg-panel-sunk hover:text-ink"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={loading || !new_tag_name}
                className="flex-1 rounded-[11px]"
              >
                {loading ? 'Creating...' : 'Create Tag'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
