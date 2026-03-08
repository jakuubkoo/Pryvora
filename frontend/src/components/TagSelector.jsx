import { useState, useEffect } from 'react'
import { X, Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useAuth } from '@/contexts/AuthContext'

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

  useEffect(() =>
  {
    fetch_tags()
  }, [])

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
      <Label className="text-sm text-[#e5e5e5]">Tags</Label>
      
      <div className="flex flex-wrap gap-2 mb-2">
        {selected_tags.map(tag => (
          <Badge
            key={tag.id}
            className="gap-1 pr-1"
            style={{ backgroundColor: tag.color, color: '#fff' }}
          >
            {tag.name}
            <button
              type="button"
              onClick={() => handle_tag_remove(tag.id)}
              className="ml-1 rounded-full hover:bg-black/20 p-0.5"
            >
              <X className="size-3"/>
            </button>
          </Badge>
        ))}
      </div>

      <div className="relative">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => set_show_dropdown(!show_dropdown)}
          className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] hover:bg-[#252525]"
        >
          <Plus className="size-4 mr-1"/>
          Add Tag
        </Button>

        {show_dropdown && (
          <div className="absolute z-10 mt-1 w-64 rounded-md border border-[#2a2a2a] bg-[#1a1a1a] shadow-lg">
            <div className="p-2 max-h-48 overflow-y-auto">
              {unselected_tags.length > 0 ? (
                unselected_tags.map(tag => (
                  <button
                    key={tag.id}
                    type="button"
                    onClick={() => handle_tag_select(tag)}
                    className="w-full text-left px-3 py-2 rounded hover:bg-[#252525] flex items-center gap-2"
                  >
                    <div
                      className="size-3 rounded-full"
                      style={{ backgroundColor: tag.color }}
                    />
                    <span className="text-sm text-[#e5e5e5]">{tag.name}</span>
                  </button>
                ))
              ) : (
                <p className="text-sm text-[#666666] px-3 py-2">No tags available</p>
              )}
            </div>

            <div className="border-t border-[#2a2a2a] p-2">
              <button
                type="button"
                onClick={() => {
                  set_show_dropdown(false)
                  set_show_create_dialog(true)
                }}
                className="w-full text-left px-3 py-2 rounded hover:bg-[#252525] flex items-center gap-2 text-sm text-[#888888]"
              >
                <Plus className="size-4"/>
                Create New Tag
              </button>
            </div>
          </div>
        )}
      </div>

      <Dialog open={show_create_dialog} onOpenChange={set_show_create_dialog}>
        <DialogContent className="sm:max-w-[425px] bg-[#0f0f0f] border-[#1a1a1a]">
          <DialogHeader>
            <DialogTitle className="text-[#e5e5e5]">Create New Tag</DialogTitle>
            <DialogDescription className="text-[#888888]">
              Add a new tag with a custom color
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handle_create_tag} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="tag_name" className="text-sm text-[#e5e5e5]">
                Tag Name
              </Label>
              <Input
                id="tag_name"
                value={new_tag_name}
                onChange={(e) => set_new_tag_name(e.target.value)}
                required
                className="bg-[#1a1a1a] border-[#2a2a2a] text-[#e5e5e5] placeholder:text-[#666666]"
                placeholder="Enter tag name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="tag_color" className="text-sm text-[#e5e5e5]">
                Tag Color
              </Label>
              <div className="flex gap-2 items-center">
                <input
                  id="tag_color"
                  type="color"
                  value={new_tag_color}
                  onChange={(e) => set_new_tag_color(e.target.value)}
                  className="h-10 w-20 rounded border border-[#2a2a2a] bg-[#1a1a1a] cursor-pointer"
                />
                <div className="flex items-center gap-2 flex-1">
                  <div
                    className="size-6 rounded-full border border-[#2a2a2a]"
                    style={{ backgroundColor: new_tag_color }}
                  />
                  <span className="text-sm text-[#888888]">{new_tag_color}</span>
                </div>
              </div>
            </div>

            {error && (
              <p className="text-sm text-red-400">{error}</p>
            )}

            <div className="flex gap-2">
              <Button
                type="button"
                onClick={() => set_show_create_dialog(false)}
                variant="ghost"
                className="flex-1 text-[#888888] hover:text-[#e5e5e5]"
              >
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={loading || !new_tag_name}
                className="flex-1 bg-[#e5e5e5] text-[#0a0a0a] hover:bg-[#d4d4d4]"
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

