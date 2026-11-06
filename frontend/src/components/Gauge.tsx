interface GaugeProps {
  /** Pourcentage, ou null quand il n'y a encore rien à mesurer. */
  value: number | null
  big?: boolean
  label?: string
}

export function Gauge({ value, big, label }: GaugeProps) {
  const percent = value ?? 0

  return (
    <div
      className={big ? 'gauge big' : 'gauge'}
      role="progressbar"
      aria-valuenow={value ?? undefined}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-label={label ?? 'Progression'}
    >
      <span className="bar">
        <i style={{ width: `${percent}%` }} />
      </span>
      <b>{value === null ? '—' : `${value}%`}</b>
    </div>
  )
}
