import { Navigate } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'

export default function ProtectedRoute({ children })
{
  const { user, loading } = useAuth()

  if (loading)
  {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#0f0f0f]">
        <div className="text-[#888888]">Loading...</div>
      </div>
    )
  }

  if (!user)
  {
    return <Navigate to="/login" replace/>
  }

  return children
}

