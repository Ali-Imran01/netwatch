export default function UtilizationBar({ value }: { value: number }) {
  const colour = value >= 90 ? 'bg-red-500' : value >= 70 ? 'bg-amber-500' : 'bg-emerald-500'

  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-28 overflow-hidden rounded bg-gray-200" role="progressbar" aria-valuenow={value} aria-valuemin={0} aria-valuemax={100}>
        <div className={`h-full ${colour}`} style={{ width: `${Math.min(value, 100)}%` }} />
      </div>
      <span className="w-12 text-right tabular-nums">{value}%</span>
    </div>
  )
}
