export type Unit = 'per_property' | 'per_place' | 'per_screen' | 'per_mailbox' | 'flat'
export interface Brick { id: string, code: string, name: string, family: 'middleware' | 'business', depends: string[], unit: Unit, monthlyPriceCents: number, description: string | null, position: number, active: boolean }
export interface Option { id: string, code: string, name: string, brick: string, grants: string | null, unit: Unit, monthlyPriceCents: number, active: boolean }
export interface Plan { id: string, code: string, name: string, bricks: string[], unit: Unit, unitPriceCents: number, description: string | null, active: boolean }
export interface Pricing { minimumMonthlyCents: number, volumeTiers: { min: number, percent: number }[], yearlyFreeMonths: number, trialDays: number, currency: string }
export interface Catalogue { bricks: Brick[], options: Option[], plans: Plan[], pricing: Pricing }
export type Quantities = Record<'properties' | 'places' | 'screens' | 'mailboxes', number>
export interface Subscription { id: string, account: string, plan: string | null, bricks: string[], options: string[], quantities: Quantities, period: 'monthly' | 'yearly', startsAt: string, endsAt: string | null, current?: boolean }
export interface QuoteLine { kind: 'plan' | 'brick' | 'option', code: string, name: string, unitLabel: string, quantity: number, unitPriceCents: number, totalCents: number }
export interface Quote { lines: QuoteLine[], enabledBricks: string[], subtotalCents: number, discountPercent: number, discountCents: number, minimumTopUpCents: number, monthlyCents: number, period: string, yearlyFreeMonths: number, periodTotalCents: number, monthlyEquivalentCents: number, errors?: string[] }
export interface Member { id: string, email: string, name: string | null, role: 'owner' | 'admin' | 'member', verified: boolean, hasPassword: boolean }
export interface Entitlements { active: boolean, plan: string | null, bricks: string[], options: string[], quotas: Quantities, expiresAt: string }
export type AccountStatus = 'trial' | 'active' | 'suspended' | 'closed'
export interface Account { id: string, slug: string, name: string, status: AccountStatus, trialEndsAt: string | null, billingName: string | null, billingEmail: string | null, billingAddress: string | null, country: string | null, vatNumber: string | null, notes: string | null, source: 'signup' | null, isNew: boolean, createdAt: string | null }
export interface AccountRow extends Account { subscription: Subscription | null, monthlyCents: number | null, members: number }
export interface AccountDetail extends Account { members: Member[], subscriptions: Subscription[], subscription: Subscription | null, quote: Quote | null, entitlements: Entitlements }
export interface AuditEntry { id: string, occurredAt: string, actor: string | null, action: string, subjectType: string, subjectId: string, account: string | null, data: Record<string, unknown> }
export interface Overview { mrrCents: number, arrCents: number, trialPipelineCents: number, accountsByStatus: Record<AccountStatus, number>, trialsEnding: { slug: string, name: string, trialEndsAt: string | null, monthlyCents: number }[], bricksInUse: Record<string, number> }
// Public sign-up (/inscription): catalogue without internal fields, and the sign-up result.
export type PublicPlan = Omit<Plan, 'id' | 'active'>
export type PublicBrick = Omit<Brick, 'id' | 'active' | 'position'>
export type PublicOption = Omit<Option, 'id' | 'active'>
export interface PublicCatalogue { plans: PublicPlan[], bricks: PublicBrick[], options: PublicOption[], pricing: Pricing }
export interface NextStep { key: string, label: string }
export interface SignupResult {
  created: boolean
  account: { slug: string, name: string, status: AccountStatus, trialEndsAt: string | null, country: string | null }
  subscription: Subscription | null
  owner: { email: string, name: string | null, verified: boolean }
  verification: { sent: boolean, email: string, devUrl?: string }
  nextSteps: NextStep[]
  quote?: Quote
}
