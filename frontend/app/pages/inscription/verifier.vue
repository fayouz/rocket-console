<script setup lang="ts">
import type { NextStep } from '~/types/console'

// Public: e-mail verification link of the self-service sign-up (/inscription/verifier?token=…).
definePageMeta({ layout: 'bare', public: true })
useHead({ title: 'Confirmation de l’e-mail · Rocket' })

const api = useApi()
const route = useRoute()
const state = ref<'pending' | 'ok' | 'error'>('pending')
const message = ref('')
const account = ref<{ slug: string, name: string } | null>(null)
const nextSteps = ref<NextStep[]>([])

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  try {
    const r = await api<{ verified: boolean, account: { slug: string, name: string }, nextSteps: NextStep[] }>('/api/public/verify-email', { method: 'POST', body: { token } })
    account.value = r.account
    nextSteps.value = r.nextSteps
    state.value = 'ok'
  }
  catch (error) {
    message.value = apiErrorMessage(error)
    state.value = 'error'
  }
})
</script>

<template>
  <div class="mx-auto max-w-xl px-4 py-16">
    <UCard>
      <p v-if="state === 'pending'" class="text-center text-muted">
        Vérification en cours…
      </p>
      <div v-else-if="state === 'ok'" class="grid gap-4">
        <div class="text-center">
          <UIcon name="i-lucide-badge-check" class="size-10 text-success" />
          <h1 class="mt-2 text-xl font-semibold">
            Adresse confirmée
          </h1>
          <p class="text-muted">
            Le compte <b>{{ account?.name }}</b> est prêt. Voici la suite :
          </p>
        </div>
        <ol class="grid gap-2 text-sm">
          <li v-for="(s, i) in nextSteps" :key="s.key" class="flex items-center gap-2">
            <UBadge :label="String(i + 1)" variant="subtle" />
            {{ s.label }}
          </li>
        </ol>
      </div>
      <div v-else class="grid gap-3 text-center">
        <UIcon name="i-lucide-circle-x" class="mx-auto size-10 text-error" />
        <p>{{ message }}</p>
        <UButton to="/inscription" variant="outline" label="Recommencer l’inscription" class="justify-self-center" />
      </div>
    </UCard>
  </div>
</template>
