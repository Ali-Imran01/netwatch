import { stateLabel } from '../api/incidents'

const stateTone: Record<string, string> = {
  detected: 'bg-error-container text-on-error-container',
  acknowledged: 'bg-warning-container text-on-warning-container',
  investigating: 'bg-warning-container text-on-warning-container',
  escalated: 'bg-warning-container text-on-warning-container',
  monitoring: 'bg-primary-container text-on-primary-container',
  resolved: 'bg-success-container text-on-success-container',
  closed: 'bg-neutral-container text-on-surface',
}

const severityTone: Record<string, string> = {
  critical: 'bg-error text-white',
  major: 'border border-outline-variant text-on-surface',
  minor: 'border border-outline-variant text-on-surface-variant',
}

export function StateChip({ state }: { state: string }) {
  return <span className={`chip ${stateTone[state] ?? 'bg-neutral-container'}`}>{stateLabel[state] ?? state}</span>
}

export function SeverityChip({ severity }: { severity: string }) {
  return <span className={`chip capitalize ${severityTone[severity] ?? severityTone.minor}`}>{severity}</span>
}
