/** Placeholder block shown while data loads. Pulses unless the person prefers reduced motion. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`rounded-lg bg-neutral-container motion-safe:animate-pulse ${className}`} />
}

/** A monitor-list row: avatar, two text lines, status chip. Same height as the real row so nothing jumps. */
export function MonitorRowSkeleton() {
  return (
    <div className="flex min-h-18 items-center gap-4 border-t border-neutral-container px-4 py-2 sm:px-6">
      <Skeleton className="size-10 shrink-0 rounded-full" />
      <div className="flex-1 space-y-2">
        <Skeleton className="h-4 w-48 max-w-full" />
        <Skeleton className="h-3 w-32 max-w-full" />
      </div>
      <Skeleton className="h-7 w-16" />
    </div>
  )
}
