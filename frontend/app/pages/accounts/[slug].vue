<script setup lang="ts">
import type { AccountDetail, AuditEntry, Catalogue, Member, Quote, Quantities } from '~/types/console'

// One account: billing details, members, subscription (edited with a live quote), entitlements preview, licence key.
const api = useApi()
const toast = useToast()
const slug = useRoute().params.slug as string

const { data: account, refresh } = await useAsyncData(`account-${slug}`, () => api<AccountDetail>(`/api/accounts/${slug}`))
const { data: catalogue } = await useAsyncData('catalogue', () => api<Catalogue>('/api/catalogue'))
const { data: audit, refresh: refreshAudit } = await useAsyncData(`audit-${slug}`, () => api<AuditEntry[]>('/api/audit-logs', { query: { account: slug, limit: 30 } }), { default: () => [] })
useHead({ title: () => `${account.value?.name ?? slug} · ${useAppConfig().rocket.name}` })

const fail = (title: string) => (error: unknown) => toast.add({ title, description: apiErrorMessage(error), color: 'error' })

// Account details
const info = reactive({ name: '', status: 'trial', trialEndsAt: '', billingName: '', billingEmail: '', billingAddress: '', country: '', vatNumber: '', notes: '' })
watchEffect(() => {
  const a = account.value
  if (a) Object.assign(info, { name: a.name, status: a.status, trialEndsAt: a.trialEndsAt?.slice(0, 10) ?? '', billingName: a.billingName ?? '', billingEmail: a.billingEmail ?? '', billingAddress: a.billingAddress ?? '', country: a.country ?? '', vatNumber: a.vatNumber ?? '', notes: a.notes ?? '' })
})
async function saveInfo() {
  await api(`/api/accounts/${slug}`, { method: 'PATCH', body: { ...info, trialEndsAt: info.trialEndsAt || null } }).then(() => toast.add({ title: 'Compte enregistré', color: 'success' })).catch(fail('Non enregistré'))
  await Promise.all([refresh(), refreshAudit()])
}

// Members
const member = reactive({ email: '', role: 'member' as Member['role'] })
const roles = [{ label: 'Propriétaire', value: 'owner' }, { label: 'Admin', value: 'admin' }, { label: 'Membre', value: 'member' }]
async function addMember() {
  await api(`/api/accounts/${slug}/members`, { method: 'POST', body: member }).then(() => { member.email = '' }).catch(fail('Membre non ajouté'))
  await refresh()
}
async function removeMember(m: Member) {
  if (!confirm(`Retirer ${m.email} ?`)) return
  await api(`/api/accounts/${slug}/members/${m.id}`, { method: 'DELETE' }).catch(fail('Non retiré'))
  await refresh()
}

// Self-service sign-up: the operator acknowledges it (the « Nouveau » badge goes away)
async function markReviewed() {
  await api(`/api/accounts/${slug}/signup-reviewed`, { method: 'POST' }).catch(fail('Non enregistré'))
  await refresh()
}

// Subscription editor + live quote
const draft = reactive({ plan: '' as string, bricks: [] as string[], options: [] as string[], quantities: { properties: 0, places: 0, screens: 0, mailboxes: 0 } as Quantities, period: 'monthly' as 'monthly' | 'yearly' })
function loadDraft() {
  const s = account.value?.subscription
  Object.assign(draft, { plan: s?.plan ?? '', bricks: [...(s?.bricks ?? [])], options: [...(s?.options ?? [])], quantities: { ...draft.quantities, ...(s?.quantities ?? {}) }, period: s?.period ?? 'monthly' })
}
loadDraft()
const planItems = computed(() => [{ label: 'Aucune offre (à la carte)', value: '' }, ...(catalogue.value?.plans.filter(p => p.active).map(p => ({ label: `${p.name} · ${euros(p.unitPriceCents)} ${unitLabels[p.unit]}`, value: p.code })) ?? [])])
const brickItems = computed(() => catalogue.value?.bricks.filter(b => b.active).map(b => ({ label: `${b.name} · ${euros(b.monthlyPriceCents)} ${unitLabels[b.unit]}`, value: b.code })) ?? [])
const optionItems = computed(() => catalogue.value?.options.filter(o => o.active).map(o => ({ label: `${o.name} (${o.brick}) · ${euros(o.monthlyPriceCents)} ${unitLabels[o.unit]}`, value: o.code })) ?? [])
const body = () => ({ ...draft, plan: draft.plan || null })
const quote = ref<Quote | null>(null)
const quoteError = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
watch(draft, () => {
  clearTimeout(timer)
  timer = setTimeout(computeQuote, 300)
}, { deep: true })
async function computeQuote() {
  try {
    quote.value = await api<Quote>('/api/quote', { method: 'POST', body: body() })
    quoteError.value = ''
  }
  catch (error) {
    quote.value = null
    quoteError.value = apiErrorMessage(error)
  }
}
onMounted(computeQuote)
async function saveSubscription(asNew: boolean) {
  const s = account.value?.subscription
  const request = asNew || !s
    ? api(`/api/accounts/${slug}/subscriptions`, { method: 'POST', body: body() })
    : api(`/api/subscriptions/${s.id}`, { method: 'PATCH', body: body() })
  await request.then(() => toast.add({ title: 'Abonnement enregistré', color: 'success' })).catch(fail('Abonnement refusé'))
  await Promise.all([refresh(), refreshAudit()])
}

// Licence key
const licence = ref('')
async function issueLicence() {
  try {
    licence.value = (await api<{ token: string }>(`/api/accounts/${slug}/licence`)).token
    const url = URL.createObjectURL(new Blob([licence.value], { type: 'text/plain' }))
    Object.assign(document.createElement('a'), { href: url, download: `rocket-licence-${slug}.jwt` }).click()
    URL.revokeObjectURL(url)
    await refreshAudit()
  }
  catch (error) {
    fail('Licence non émise')(error)
  }
}
</script>

<template>
  <UDashboardPanel id="account">
    <template #header>
      <UDashboardNavbar :title="account?.name ?? slug">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UBadge v-if="account?.isNew" color="primary" label="Nouveau" />
          <UButton v-if="account?.isNew" icon="i-lucide-check" label="Inscription vue" variant="ghost" @click="markReviewed" />
          <UBadge v-else-if="account?.source === 'signup'" color="neutral" variant="outline" label="Inscription en ligne" />
          <UBadge v-if="account" :color="statusColors[account.status]" variant="subtle" :label="statusLabels[account.status]" />
          <UButton icon="i-lucide-key-round" label="Licence" variant="outline" @click="issueLicence" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="account" class="grid gap-4 lg:grid-cols-2">
        <UCard>
          <template #header><b>Abonnement</b></template>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="Offre" class="sm:col-span-2"><USelect v-model="draft.plan" :items="planItems" class="w-full" /></UFormField>
            <UFormField label="Briques à la carte" class="sm:col-span-2"><USelectMenu v-model="draft.bricks" :items="brickItems" value-key="value" multiple class="w-full" /></UFormField>
            <UFormField label="Options" class="sm:col-span-2"><USelectMenu v-model="draft.options" :items="optionItems" value-key="value" multiple class="w-full" /></UFormField>
            <UFormField label="Logements"><UInputNumber v-model="draft.quantities.properties" :min="0" /></UFormField>
            <UFormField label="Lieux"><UInputNumber v-model="draft.quantities.places" :min="0" /></UFormField>
            <UFormField label="Écrans"><UInputNumber v-model="draft.quantities.screens" :min="0" /></UFormField>
            <UFormField label="Boîtes partagées"><UInputNumber v-model="draft.quantities.mailboxes" :min="0" /></UFormField>
            <UFormField label="Période"><USelect v-model="draft.period" :items="[{ label: 'Mensuelle', value: 'monthly' }, { label: 'Annuelle', value: 'yearly' }]" class="w-full" /></UFormField>
          </div>
          <UAlert v-if="quoteError" class="mt-3" color="error" variant="subtle" :description="quoteError" />
          <table v-if="quote" class="mt-3 w-full text-sm">
            <tbody class="divide-y divide-default">
              <tr v-for="l in quote.lines" :key="l.kind + l.code">
                <td>{{ l.name }} <span class="text-muted">× {{ l.quantity }} {{ l.unitLabel }}</span></td>
                <td class="text-right">{{ euros(l.totalCents) }}</td>
              </tr>
              <tr v-if="quote.discountCents"><td>Remise volume {{ quote.discountPercent }} %</td><td class="text-right">− {{ euros(quote.discountCents) }}</td></tr>
              <tr v-if="quote.minimumTopUpCents"><td>Complément minimum mensuel</td><td class="text-right">{{ euros(quote.minimumTopUpCents) }}</td></tr>
              <tr><td><b>Par mois (HT)</b></td><td class="text-right"><b>{{ euros(quote.monthlyCents) }}</b></td></tr>
              <tr v-if="quote.period === 'yearly'"><td>Par an ({{ quote.yearlyFreeMonths }} mois offerts)</td><td class="text-right"><b>{{ euros(quote.periodTotalCents) }}</b></td></tr>
            </tbody>
          </table>
          <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
              <UButton variant="ghost" label="Annuler" @click="loadDraft" />
              <UButton v-if="account.subscription" variant="outline" label="Nouvel abonnement (dès aujourd’hui)" :disabled="!quote" @click="saveSubscription(true)" />
              <UButton :label="account.subscription ? 'Modifier l’abonnement' : 'Souscrire'" :disabled="!quote" @click="saveSubscription(false)" />
            </div>
          </template>
        </UCard>

        <UCard>
          <template #header><b>Droits (entitlements)</b></template>
          <p class="text-sm">
            <UBadge :color="account.entitlements.active ? 'success' : 'neutral'" variant="subtle" :label="account.entitlements.active ? 'Actifs' : 'Aucun droit'" />
            <span class="text-muted"> · valables jusqu’au {{ dateFr(account.entitlements.expiresAt) }}</span>
          </p>
          <div class="mt-2 flex flex-wrap gap-1">
            <UBadge v-for="b in account.entitlements.bricks" :key="b" variant="outline" :label="b" />
          </div>
          <p class="mt-2 text-sm text-muted">
            Options : {{ account.entitlements.options.join(', ') || '—' }} · Quotas : {{ account.entitlements.quotas.properties }} logement(s), {{ account.entitlements.quotas.places }} lieu(x), {{ account.entitlements.quotas.screens }} écran(s), {{ account.entitlements.quotas.mailboxes }} boîte(s)
          </p>
          <p class="mt-2 text-xs text-muted">Lu par les briques : <code>GET /api/entitlements/{{ account.slug }}</code> (jeton d’application).</p>
          <UTextarea v-if="licence" :model-value="licence" readonly autoresize class="mt-3 w-full font-mono text-xs" />
        </UCard>

        <UCard>
          <template #header><b>Compte et facturation</b></template>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="Nom"><UInput v-model="info.name" class="w-full" /></UFormField>
            <UFormField label="Statut"><USelect v-model="info.status" :items="Object.entries(statusLabels).map(([value, label]) => ({ value, label }))" class="w-full" /></UFormField>
            <UFormField label="Fin d’essai"><UInput v-model="info.trialEndsAt" type="date" class="w-full" /></UFormField>
            <UFormField label="Pays (ISO)"><UInput v-model="info.country" maxlength="2" class="w-full" /></UFormField>
            <UFormField label="Nom de facturation"><UInput v-model="info.billingName" class="w-full" /></UFormField>
            <UFormField label="E-mail de facturation"><UInput v-model="info.billingEmail" type="email" class="w-full" /></UFormField>
            <UFormField label="Adresse" class="sm:col-span-2"><UInput v-model="info.billingAddress" class="w-full" /></UFormField>
            <UFormField label="N° de TVA"><UInput v-model="info.vatNumber" class="w-full" /></UFormField>
            <UFormField label="Notes" class="sm:col-span-2"><UTextarea v-model="info.notes" class="w-full" /></UFormField>
          </div>
          <template #footer><div class="flex justify-end"><UButton label="Enregistrer" @click="saveInfo" /></div></template>
        </UCard>

        <UCard>
          <template #header><b>Membres</b></template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="m in account.members" :key="m.id" class="flex items-center justify-between py-2">
              <span>{{ m.email }} <span class="text-muted">· {{ roles.find(r => r.value === m.role)?.label }}</span>
                <UBadge v-if="!m.verified" class="ml-2" color="warning" variant="subtle" label="E-mail non vérifié" />
              </span>
              <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Retirer" @click="removeMember(m)" />
            </li>
          </ul>
          <div class="mt-3 flex flex-wrap gap-2">
            <UInput v-model="member.email" type="email" placeholder="e-mail" class="flex-1" />
            <USelect v-model="member.role" :items="roles" class="w-36" />
            <UButton icon="i-lucide-user-plus" label="Ajouter" :disabled="!member.email" @click="addMember" />
          </div>
        </UCard>

        <UCard>
          <template #header><b>Historique des abonnements</b></template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="s in account.subscriptions" :key="s.id" class="py-2">
              <b>{{ s.plan ?? 'À la carte' }}</b>
              <span class="text-muted"> · {{ [...s.bricks, ...s.options].join(', ') }} · {{ s.period === 'yearly' ? 'annuel' : 'mensuel' }} · du {{ dateFr(s.startsAt) }}{{ s.endsAt ? ` au ${dateFr(s.endsAt)}` : '' }}</span>
              <UBadge v-if="s.current" class="ml-2" color="success" variant="subtle" label="En cours" />
            </li>
          </ul>
        </UCard>

        <UCard>
          <template #header><b>Journal</b></template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="e in audit" :key="e.id" class="py-1">
              <span class="text-muted">{{ new Date(e.occurredAt).toLocaleString('fr-FR') }}</span> · <code>{{ e.action }}</code> · {{ e.actor }}
            </li>
          </ul>
        </UCard>
      </div>
    </template>
  </UDashboardPanel>
</template>
