<script setup lang="ts">
import type { AuditEntry } from '~/types/console'

// Audit log of the console: every change (who, what, data sent), newest first.
const api = useApi()
useHead({ title: `Journal · ${useAppConfig().rocket.name}` })
const account = ref('')
const { data: entries, refresh } = await useAsyncData('audit', () => api<AuditEntry[]>('/api/audit-logs', { query: { account: account.value || undefined, limit: 200 } }), { default: () => [] })
</script>

<template>
  <UDashboardPanel id="journal">
    <template #header>
      <UDashboardNavbar title="Journal">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <UInput v-model="account" icon="i-lucide-filter" placeholder="Compte (slug)" class="max-w-xs" @keyup.enter="refresh()" />
      <UCard>
        <ul class="divide-y divide-default text-sm">
          <li v-for="e in entries" :key="e.id" class="py-2">
            <span class="text-muted">{{ new Date(e.occurredAt).toLocaleString('fr-FR') }}</span> · <code>{{ e.action }}</code> · {{ e.subjectType }} {{ e.subjectId }}
            <NuxtLink v-if="e.account" :to="`/accounts/${e.account}`" class="text-primary"> · {{ e.account }}</NuxtLink>
            <span class="text-muted"> · {{ e.actor }}</span>
            <pre v-if="Object.keys(e.data).length" class="mt-1 overflow-x-auto text-xs text-muted">{{ JSON.stringify(e.data) }}</pre>
          </li>
        </ul>
        <p v-if="!entries.length" class="text-sm text-muted">Rien pour l’instant.</p>
      </UCard>
    </template>
  </UDashboardPanel>
</template>
