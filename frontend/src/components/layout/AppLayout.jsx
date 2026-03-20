import Sidebar from './Sidebar'
import Topbar from './Topbar'

export default function AppLayout({ children, title })
{
  return (
    <div className="flex h-screen bg-[#0f0f0f] overflow-hidden">
      <Sidebar/>
      <div className="flex-1 flex flex-col overflow-hidden">
        <Topbar title={title}/>
        <main className="flex-1 overflow-y-auto bg-[#0f0f0f]">
          {children}
        </main>
      </div>
    </div>
  )
}

