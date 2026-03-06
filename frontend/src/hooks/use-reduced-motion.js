import { useEffect, useState } from 'react'

export function use_reduced_motion()
{
  const [should_reduce_motion, set_should_reduce_motion] = useState(false)

  useEffect(() =>
  {
    const media_query = window.matchMedia('(prefers-reduced-motion: reduce)')
    set_should_reduce_motion(media_query.matches)

    const handle_change = (event) =>
    {
      set_should_reduce_motion(event.matches)
    }

    media_query.addEventListener('change', handle_change)

    return () =>
    {
      media_query.removeEventListener('change', handle_change)
    }
  }, [])

  return should_reduce_motion
}


