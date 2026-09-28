import type { AccountStatus } from '~/types/console'

export const euros = (cents: number | null | undefined) => cents == null ? '—' : `${(cents / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`

export const statusLabels: Record<AccountStatus, string> = { trial: 'Essai', active: 'Actif', suspended: 'Suspendu', closed: 'Clôturé' }
export const statusColors: Record<AccountStatus, 'warning' | 'success' | 'error' | 'neutral'> = { trial: 'warning', active: 'success', suspended: 'error', closed: 'neutral' }
export const unitLabels: Record<string, string> = { per_property: '/ logement', per_place: '/ lieu', per_screen: '/ écran', per_mailbox: '/ boîte', flat: 'forfait' }
export const units = Object.keys(unitLabels).map(value => ({ value, label: unitLabels[value]! }))
export const dateFr = (iso: string | null | undefined) => iso ? new Date(iso).toLocaleDateString('fr-FR') : '—'
