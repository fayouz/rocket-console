<script setup lang="ts">
import type { Overview } from '~/types/console'

// Operator figures: MRR / ARR estimated from the quotes of the active accounts, trials pipeline, bricks in use.
const api = useApi()
useHead({ title: `Revenus · ${useAppConfig().rocket.name}` })
const { data: o } = await useAsyncData('overview', () => api<Overview>('/api/overview'))
</script>

<template>
  <UDashboardPanel id="revenus">
    <template #header>
      <UDashboardNavbar title="Revenus">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <div v-if="o" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <UCard><p class="text-sm text-muted">MRR estimé (HT)</p><p class="text-2xl font-semibold">{{ euros(o.mrrCents) }}</p></UCard>
        <UCard><p class="text-sm text-muted">ARR estimé</p><p class="text-2xl font-semibold">{{ euros(o.arrCents) }}</p></UCard>
        <UCard><p class="text-sm text-muted">Essais (potentiel mensuel)</p><p class="text-2xl font-semibold">{{ euros(o.trialPipelineCents) }}</p></UCard>
        <UCard>
          <p class="text-sm text-muted">Comptes</p>
          <p class="text-sm"><span v-for="(n, s) in o.accountsByStatus" :key="s" class="mr-2">{{ statusLabels[s] }} <b>{{ n }}</b></span></p>
        </UCard>
        <UCard class="sm:col-span-2">
          <template #header><b>Essais qui se terminent</b></template>
          <ul class="divide-y divide-default text-sm">
            <li v-for="t in o.trialsEnding" :key="t.slug" class="flex justify-between py-2">
              <NuxtLink :to="`/accounts/${t.slug}`" class="hover:underline">{{ t.name }}</NuxtLink>
              <span>{{ dateFr(t.trialEndsAt) }} · {{ euros(t.monthlyCents) }}/mois</span>
            </li>
          </ul>
          <p v-if="!o.trialsEnding.length" class="text-sm text-muted">Aucun essai en cours.</p>
        </UCard>
        <UCard class="sm:col-span-2">
          <template #header><b>Briques en service</b></template>
          <div class="flex flex-wrap gap-2">
            <UBadge v-for="(n, b) in o.bricksInUse" :key="b" variant="outline" :label="`${b} · ${n}`" />
          </div>
        </UCard>
      </div>
    </template>
  </UDashboardPanel>
</template>
