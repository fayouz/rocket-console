<script setup lang="ts">
import type { Brick, Catalogue, Option, Plan, Pricing } from '~/types/console'

// The catalogue: bricks, options, plans and pricing rules, edited in place (prices in euros, stored in cents).
const api = useApi()
const toast = useToast()
useHead({ title: `Catalogue · ${useAppConfig().rocket.name}` })
const { data: catalogue, refresh } = await useAsyncData('catalogue', () => api<Catalogue>('/api/catalogue'))
const brickCodes = computed(() => catalogue.value?.bricks.map(b => ({ label: b.name, value: b.code })) ?? [])
const fail = (error: unknown) => toast.add({ title: 'Non enregistré', description: apiErrorMessage(error), color: 'error' })

type Kind = 'bricks' | 'options' | 'plans'
const open = ref(false)
const kind = ref<Kind>('bricks')
const isNew = ref(true)
const form = reactive({ code: '', name: '', family: 'business', unit: 'per_place', price: 0, depends: [] as string[], brick: '', grants: '', bricks: [] as string[], description: '', active: true })
function edit(k: Kind, item?: Brick | Option | Plan) {
  kind.value = k
  isNew.value = !item
  Object.assign(form, { code: '', name: '', family: 'business', unit: k === 'bricks' ? 'per_place' : 'per_property', price: 0, depends: [], brick: '', grants: '', bricks: [], description: '', active: true })
  if (item) {
    Object.assign(form, { code: item.code, name: item.name, unit: item.unit, active: item.active })
    if ('family' in item) Object.assign(form, { family: item.family, depends: [...item.depends], price: item.monthlyPriceCents / 100, description: item.description ?? '' })
    if ('grants' in item) Object.assign(form, { brick: item.brick, grants: item.grants ?? '', price: item.monthlyPriceCents / 100 })
    if ('unitPriceCents' in item) Object.assign(form, { bricks: [...item.bricks], price: item.unitPriceCents / 100, description: item.description ?? '' })
  }
  open.value = true
}
async function save() {
  const cents = Math.round(Number(form.price) * 100)
  const body = kind.value === 'bricks'
    ? { name: form.name, family: form.family, unit: form.unit, monthlyPriceCents: cents, depends: form.depends, description: form.description || null, active: form.active }
    : kind.value === 'options'
      ? { name: form.name, brick: form.brick, grants: form.grants || null, unit: form.unit, monthlyPriceCents: cents, active: form.active }
      : { name: form.name, bricks: form.bricks, unit: form.unit, unitPriceCents: cents, description: form.description || null, active: form.active }
  try {
    await api(isNew.value ? `/api/catalogue/${kind.value}` : `/api/catalogue/${kind.value}/${form.code}`, { method: isNew.value ? 'POST' : 'PATCH', body: isNew.value ? { code: form.code, ...body } : body })
    open.value = false
    await refresh()
  }
  catch (error) {
    fail(error)
  }
}
async function remove(k: Kind, code: string) {
  if (!confirm(`Supprimer « ${code} » ?`)) return
  await api(`/api/catalogue/${k}/${code}`, { method: 'DELETE' }).catch(fail)
  await refresh()
}

const pricing = reactive({ minimum: 0, yearlyFreeMonths: 0, trialDays: 0, tiers: '' })
watchEffect(() => {
  const p = catalogue.value?.pricing
  if (p) Object.assign(pricing, { minimum: p.minimumMonthlyCents / 100, yearlyFreeMonths: p.yearlyFreeMonths, trialDays: p.trialDays, tiers: p.volumeTiers.map(t => `${t.min}:${t.percent}`).join(', ') })
})
async function savePricing() {
  const volumeTiers = pricing.tiers.split(',').map(s => s.trim()).filter(Boolean).map((s) => {
    const [min, percent] = s.split(':').map(Number)
    return { min, percent }
  })
  await api<Pricing>('/api/catalogue/pricing', { method: 'PUT', body: { minimumMonthlyCents: Math.round(pricing.minimum * 100), yearlyFreeMonths: pricing.yearlyFreeMonths, trialDays: pricing.trialDays, volumeTiers } })
    .then(() => toast.add({ title: 'Règles enregistrées', color: 'success' })).catch(fail)
  await refresh()
}
</script>

<template>
  <UDashboardPanel id="catalogue">
    <template #header>
      <UDashboardNavbar title="Catalogue">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="catalogue" class="grid gap-4 lg:grid-cols-2">
        <UCard class="lg:col-span-2">
          <template #header>
            <div class="flex items-center justify-between"><b>Briques</b><UButton size="sm" icon="i-lucide-plus" label="Brique" @click="edit('bricks')" /></div>
          </template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="b in catalogue.bricks" :key="b.code" class="flex flex-wrap items-center justify-between gap-2 py-2" :class="{ 'opacity-50': !b.active }">
              <span>
                <b>{{ b.name }}</b> <span class="text-muted">· {{ b.code }} · {{ b.family === 'middleware' ? 'middleware' : 'métier' }}{{ b.depends.length ? ` · requiert ${b.depends.join(', ')}` : '' }}</span>
              </span>
              <span class="flex items-center gap-1">
                <b>{{ euros(b.monthlyPriceCents) }}</b> <span class="text-muted">{{ unitLabels[b.unit] }}</span>
                <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit('bricks', b)" />
                <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove('bricks', b.code)" />
              </span>
            </li>
          </ul>
        </UCard>
        <UCard>
          <template #header>
            <div class="flex items-center justify-between"><b>Offres</b><UButton size="sm" icon="i-lucide-plus" label="Offre" @click="edit('plans')" /></div>
          </template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="p in catalogue.plans" :key="p.code" class="flex flex-wrap items-center justify-between gap-2 py-2" :class="{ 'opacity-50': !p.active }">
              <span><b>{{ p.name }}</b> <span class="text-muted">· {{ p.bricks.join(' + ') }}</span></span>
              <span class="flex items-center gap-1">
                <b>{{ euros(p.unitPriceCents) }}</b> <span class="text-muted">{{ unitLabels[p.unit] }}</span>
                <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit('plans', p)" />
                <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove('plans', p.code)" />
              </span>
            </li>
          </ul>
        </UCard>
        <UCard>
          <template #header>
            <div class="flex items-center justify-between"><b>Options</b><UButton size="sm" icon="i-lucide-plus" label="Option" @click="edit('options')" /></div>
          </template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="o in catalogue.options" :key="o.code" class="flex flex-wrap items-center justify-between gap-2 py-2" :class="{ 'opacity-50': !o.active }">
              <span><b>{{ o.name }}</b> <span class="text-muted">· {{ o.brick }}{{ o.grants ? ` → ${o.grants}` : '' }}</span></span>
              <span class="flex items-center gap-1">
                <b>+{{ euros(o.monthlyPriceCents) }}</b> <span class="text-muted">{{ unitLabels[o.unit] }}</span>
                <UButton size="sm" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="edit('options', o)" />
                <UButton size="sm" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="remove('options', o.code)" />
              </span>
            </li>
          </ul>
        </UCard>
        <UCard class="lg:col-span-2">
          <template #header><b>Règles de prix</b></template>
          <div class="grid gap-3 sm:grid-cols-4">
            <UFormField label="Minimum mensuel (€)"><UInputNumber v-model="pricing.minimum" :min="0" :step="1" /></UFormField>
            <UFormField label="Remises volume" help="min:pourcentage, séparés par des virgules"><UInput v-model="pricing.tiers" class="w-full" /></UFormField>
            <UFormField label="Mois offerts (annuel)"><UInputNumber v-model="pricing.yearlyFreeMonths" :min="0" :max="11" /></UFormField>
            <UFormField label="Jours d’essai"><UInputNumber v-model="pricing.trialDays" :min="0" /></UFormField>
          </div>
          <template #footer><div class="flex justify-end"><UButton label="Enregistrer" @click="savePricing" /></div></template>
        </UCard>
      </div>
    </template>

    <UModal v-model:open="open" :title="isNew ? 'Ajouter' : `Modifier ${form.code}`">
      <template #body>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField v-if="isNew" label="Code"><UInput v-model="form.code" class="w-full" /></UFormField>
          <UFormField label="Nom"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField v-if="kind === 'bricks'" label="Famille"><USelect v-model="form.family" :items="[{ label: 'Métier', value: 'business' }, { label: 'Middleware', value: 'middleware' }]" class="w-full" /></UFormField>
          <UFormField label="Unité"><USelect v-model="form.unit" :items="units" class="w-full" /></UFormField>
          <UFormField label="Prix mensuel (€ HT)"><UInputNumber v-model="form.price" :min="0" :step="0.5" /></UFormField>
          <UFormField v-if="kind === 'bricks'" label="Requiert" class="sm:col-span-2"><USelectMenu v-model="form.depends" :items="brickCodes" value-key="value" multiple class="w-full" /></UFormField>
          <UFormField v-if="kind === 'options'" label="Brique"><USelect v-model="form.brick" :items="brickCodes" class="w-full" /></UFormField>
          <UFormField v-if="kind === 'options'" label="Active la brique"><USelect v-model="form.grants" :items="[{ label: '—', value: '' }, ...brickCodes]" class="w-full" /></UFormField>
          <UFormField v-if="kind === 'plans'" label="Briques incluses" class="sm:col-span-2"><USelectMenu v-model="form.bricks" :items="brickCodes" value-key="value" multiple class="w-full" /></UFormField>
          <UFormField v-if="kind !== 'options'" label="Description" class="sm:col-span-2"><UInput v-model="form.description" class="w-full" /></UFormField>
          <UCheckbox v-model="form.active" label="Actif (proposé aux nouveaux abonnements)" class="sm:col-span-2" />
        </div>
      </template>
      <template #footer><UButton label="Enregistrer" @click="save" /></template>
    </UModal>
  </UDashboardPanel>
</template>
