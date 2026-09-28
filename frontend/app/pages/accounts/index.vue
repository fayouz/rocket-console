<script setup lang="ts">
import type { AccountRow, AccountStatus } from '~/types/console'

// Customer accounts: status, current offer, monthly amount; create a new account (trial by default).
const api = useApi()
const toast = useToast()
const route = useRoute()
useHead({ title: `Comptes · ${useAppConfig().rocket.name}` })

const status = ref<AccountStatus | 'all'>((route.query.status as AccountStatus) || 'all')
const q = ref('')
const { data: accounts, refresh } = await useAsyncData('accounts', () => api<AccountRow[]>('/api/accounts', { query: { status: status.value === 'all' ? undefined : status.value, q: q.value || undefined } }), { default: () => [], watch: [status] })
const statusItems = [{ label: 'Tous', value: 'all' }, ...Object.entries(statusLabels).map(([value, label]) => ({ label, value }))]

const open = ref(false)
const form = reactive({ name: '', slug: '', ownerEmail: '', status: 'trial' as AccountStatus })
async function create() {
  try {
    const a = await api<{ slug: string }>('/api/accounts', { method: 'POST', body: { ...form, slug: form.slug || undefined, ownerEmail: form.ownerEmail || undefined } })
    open.value = false
    await navigateTo(`/accounts/${a.slug}`)
  }
  catch (error) {
    toast.add({ title: 'Compte non créé', description: apiErrorMessage(error), color: 'error' })
  }
}
</script>

<template>
  <UDashboardPanel id="accounts">
    <template #header>
      <UDashboardNavbar title="Comptes">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton icon="i-lucide-plus" label="Nouveau compte" @click="open = true" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div class="flex flex-wrap gap-2">
        <USelect v-model="status" :items="statusItems" class="w-40" />
        <UInput v-model="q" icon="i-lucide-search" placeholder="Nom ou identifiant" @keyup.enter="refresh()" />
      </div>
      <UCard>
        <ul class="divide-y divide-default text-sm">
          <li v-for="a in accounts" :key="a.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <NuxtLink :to="`/accounts/${a.slug}`" class="hover:underline">
              <b>{{ a.name }}</b>
              <span class="text-muted"> · {{ a.slug }} · {{ a.subscription?.plan ?? (a.subscription?.bricks.join(', ') || 'sans abonnement') }} · {{ a.members }} membre(s)</span>
            </NuxtLink>
            <span class="flex items-center gap-2">
              <span v-if="a.status === 'trial'" class="text-muted">fin d’essai {{ dateFr(a.trialEndsAt) }}</span>
              <b>{{ euros(a.monthlyCents) }}</b><span class="text-muted">/ mois</span>
              <UBadge :color="statusColors[a.status]" variant="subtle" :label="statusLabels[a.status]" />
            </span>
          </li>
        </ul>
        <p v-if="!accounts.length" class="text-sm text-muted">Aucun compte.</p>
      </UCard>
    </template>

    <UModal v-model:open="open" title="Nouveau compte">
      <template #body>
        <div class="grid gap-3">
          <UFormField label="Nom"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Identifiant (slug)" help="Vide : déduit du nom. Sera l’organisation Rocket Auth."><UInput v-model="form.slug" class="w-full" /></UFormField>
          <UFormField label="E-mail du propriétaire"><UInput v-model="form.ownerEmail" type="email" class="w-full" /></UFormField>
          <UFormField label="Statut"><USelect v-model="form.status" :items="statusItems.slice(1)" class="w-full" /></UFormField>
        </div>
      </template>
      <template #footer>
        <UButton label="Créer" :disabled="!form.name" @click="create" />
      </template>
    </UModal>
  </UDashboardPanel>
</template>
