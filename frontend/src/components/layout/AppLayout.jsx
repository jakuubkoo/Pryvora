import Sidebar from './Sidebar'
import Topbar from './Topbar'

export default function AppLayout({ children, title })
{
  return (
    <div className="flex h-screen bg-[#0a0a0a] overflow-hidden">
      <Sidebar/>
      <div className="flex-1 flex flex-col overflow-hidden">
        <Topbar title={title}/>
        <main className="flex-1 overflow-y-auto">
          {children}
        </main>
      </div>
    </div>
  )
}

