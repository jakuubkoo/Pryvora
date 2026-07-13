import Masthead from './Masthead'

export default function AppLayout({ children })
{
  return (
    <div className="flex min-h-screen flex-col bg-paper text-ink">
      <Masthead/>
      <main className="flex-1 px-4 pb-12 pt-6 md:px-8">
        <div className="mx-auto w-full max-w-[1320px]">
          {children}
        </div>
      </main>
    </div>
  )
}
