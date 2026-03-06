import AppLayout from '@/components/layout/AppLayout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export default function Calendar()
{
  return (
    <AppLayout title="Calendar">
      <div className="p-6">
        <Card className="border-[#1a1a1a] bg-[#0f0f0f]">
          <CardHeader>
            <CardTitle className="text-[#e5e5e5]">Calendar</CardTitle>
            <CardDescription className="text-[#888888]">
              Coming soon
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-[#666666]">
              This feature is under development
            </p>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}

