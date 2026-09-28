<script setup lang="ts">
import type { PublicCatalogue, Quantities, Quote, SignupResult } from '~/types/console'

// Public self-service sign-up (no account): 1. offer with a live quote, 2. company and owner, 3. confirmation.
// The account starts in trial, with its subscription; the owner confirms their e-mail. No payment yet.
definePageMeta({ layout: 'bare', public: true })
useHead({ title: 'Créer un compte · Rocket' })

const api = useApi()
const route = useRoute()
const { data: catalogue, error: catalogueError } = await useAsyncData('public-catalogue', () => api<PublicCatalogue>('/api/public/catalogue'))

const step = ref(1)
const ALACARTE = '__bricks__'
const offer = reactive({
  plan: (typeof route.query.offre === 'string' ? route.query.offre : '') as string,
  bricks: [] as string[],
  options: [] as string[],
  quantities: { properties: 1, places: 0, screens: 0, mailboxes: 0 } as Quantities,
  period: 'monthly' as 'monthly' | 'yearly',
})
watchEffect(() => {
  if (!offer.plan && catalogue.value?.plans.length) offer.plan = catalogue.value.plans[0]!.code
})

const isAlaCarte = computed(() => offer.plan === ALACARTE)
const selectedPlan = computed(() => catalogue.value?.plans.find(p => p.code === offer.plan))
// Bricks covered by the offer: those of the plan, or the ones picked à la carte.
const offerBricks = computed(() => isAlaCarte.value ? offer.bricks : (selectedPlan.value?.bricks ?? []))
const availableOptions = computed(() => (catalogue.value?.options ?? []).filter(o => offerBricks.value.includes(o.brick)))
// Quantities that matter for the chosen offer (by pricing unit).
const neededUnits = computed(() => {
  const units = new Set<string>()
  if (selectedPlan.value) units.add(selectedPlan.value.unit)
  for (const code of offerBricks.value) {
    const b = catalogue.value?.bricks.find(x => x.code === code)
    if (b) units.add(b.unit)
  }
  for (const code of offer.options) {
    const o = catalogue.value?.options.find(x => x.code === code)
    if (o) units.add(o.unit)
  }
  return units
})
const quantityFields = computed(() => ([
  { key: 'properties', label: 'Logements', unit: 'per_property' },
  { key: 'places', label: 'Lieux', unit: 'per_place' },
  { key: 'screens', label: 'Écrans', unit: 'per_screen' },
  { key: 'mailboxes', label: 'Boîtes e-mail', unit: 'per_mailbox' },
] as const).filter(f => neededUnits.value.has(f.unit)))

function offerBody() {
  return {
    plan: isAlaCarte.value ? null : offer.plan,
    bricks: isAlaCarte.value ? offer.bricks : [],
    options: offer.options.filter(o => availableOptions.value.some(a => a.code === o)),
    quantities: offer.quantities,
    period: offer.period,
  }
}

// Live quote (debounced)
const quote = ref<Quote | null>(null)
const quoteError = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
async function refreshQuote() {
  if (isAlaCarte.value && !offer.bricks.length) {
    quote.value = null
    quoteError.value = 'Choisissez au moins une brique.'
    return
  }
  try {
    quote.value = await api<Quote>('/api/public/quote', { method: 'POST', body: offerBody() })
    quoteError.value = ''
  }
  catch (error) {
    quote.value = null
    quoteError.value = apiErrorMessage(error)
  }
}
watch(offer, () => {
  clearTimeout(timer)
  timer = setTimeout(refreshQuote, 250)
}, { deep: true })
onMounted(refreshQuote)

// Step 2: company and owner
const formStartedAt = Math.floor(Date.now() / 1000)
const company = reactive({ name: '', slug: '', country: 'FR', vatNumber: '' })
const slugTouched = ref(false)
const suggestedSlug = computed(() => company.name.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64))
watch(suggestedSlug, (s) => {
  if (!slugTouched.value) company.slug = s
})
const owner = reactive({ name: '', email: '', password: '', authProvider: 'password' as 'password' | 'rocket-auth' })
const acceptTerms = ref(false)
const website = ref('') // honeypot: hidden, left empty by humans
const countries = [
  { label: 'France', value: 'FR' }, { label: 'Belgique', value: 'BE' }, { label: 'Suisse', value: 'CH' },
  { label: 'Luxembourg', value: 'LU' }, { label: 'Espagne', value: 'ES' }, { label: 'Italie', value: 'IT' },
  { label: 'Portugal', value: 'PT' }, { label: 'Maroc', value: 'MA' }, { label: 'Canada', value: 'CA' },
]
const canSubmit = computed(() => company.name.trim() && owner.name.trim() && /.+@.+\..+/.test(owner.email) && acceptTerms.value
  && (owner.authProvider === 'rocket-auth' || owner.password.length >= 10))

const submitting = ref(false)
const submitError = ref('')
const result = ref<SignupResult | null>(null)
async function submit() {
  submitting.value = true
  submitError.value = ''
  try {
    result.value = await api<SignupResult>('/api/public/signup', {
      method: 'POST',
      body: {
        offer: offerBody(),
        account: { name: company.name, slug: company.slug || undefined, country: company.country || undefined, vatNumber: company.vatNumber || undefined },
        owner: { name: owner.name, email: owner.email, ...(owner.authProvider === 'rocket-auth' ? { authProvider: 'rocket-auth' } : { password: owner.password }) },
        acceptTerms: acceptTerms.value,
        formStartedAt,
        website: website.value,
      },
    })
    step.value = 3
  }
  catch (error) {
    submitError.value = apiErrorMessage(error)
  }
  finally {
    submitting.value = false
  }
}

const trialDays = computed(() => catalogue.value?.pricing.trialDays ?? 30)
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-10">
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold">
        Créer votre compte Rocket
      </h1>
      <p class="mt-2 text-muted">
        {{ trialDays }} jours d’essai gratuit, sans carte bancaire. Choisissez votre offre, vous pourrez la changer à tout moment.
      </p>
      <ol class="mt-6 flex justify-center gap-6 text-sm">
        <li v-for="(label, i) in ['Offre', 'Votre compte', 'Confirmation']" :key="label" :class="step === i + 1 ? 'font-semibold text-primary' : 'text-muted'">
          {{ i + 1 }}. {{ label }}
        </li>
      </ol>
    </header>

    <UAlert v-if="catalogueError" color="error" variant="subtle" title="Catalogue indisponible" description="Réessayez dans un instant." />

    <!-- Step 1: offer -->
    <div v-else-if="step === 1 && catalogue" class="grid gap-6 md:grid-cols-[1fr_20rem]">
      <div class="grid gap-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <button
            v-for="p in catalogue.plans"
            :key="p.code"
            type="button"
            class="rounded-lg border p-4 text-left transition"
            :class="offer.plan === p.code ? 'border-primary ring-2 ring-primary/40' : 'border-default hover:border-primary/60'"
            @click="offer.plan = p.code"
          >
            <b>{{ p.name }}</b>
            <p class="text-sm text-muted">
              {{ p.description }}
            </p>
            <p class="mt-2 text-sm">
              {{ euros(p.unitPriceCents) }} {{ unitLabels[p.unit] }} / mois
            </p>
          </button>
          <button
            type="button"
            class="rounded-lg border p-4 text-left transition"
            :class="isAlaCarte ? 'border-primary ring-2 ring-primary/40' : 'border-default hover:border-primary/60'"
            @click="offer.plan = ALACARTE"
          >
            <b>Briques à la carte</b>
            <p class="text-sm text-muted">
              Composez votre suite brique par brique.
            </p>
          </button>
        </div>

        <UCard v-if="isAlaCarte">
          <template #header>
            <b>Briques</b>
          </template>
          <div class="grid gap-2 sm:grid-cols-2">
            <UCheckbox
              v-for="b in catalogue.bricks"
              :key="b.code"
              :model-value="offer.bricks.includes(b.code)"
              :label="`${b.name} · ${euros(b.monthlyPriceCents)} ${unitLabels[b.unit]}`"
              :description="b.depends.length ? `Demande : ${b.depends.join(', ')}` : (b.description ?? undefined)"
              @update:model-value="(v: boolean | 'indeterminate') => { offer.bricks = v === true ? [...offer.bricks, b.code] : offer.bricks.filter(c => c !== b.code) }"
            />
          </div>
        </UCard>

        <UCard v-if="availableOptions.length">
          <template #header>
            <b>Options</b>
          </template>
          <div class="grid gap-2">
            <UCheckbox
              v-for="o in availableOptions"
              :key="o.code"
              :model-value="offer.options.includes(o.code)"
              :label="`${o.name} · ${euros(o.monthlyPriceCents)} ${unitLabels[o.unit]}`"
              @update:model-value="(v: boolean | 'indeterminate') => { offer.options = v === true ? [...offer.options, o.code] : offer.options.filter(c => c !== o.code) }"
            />
          </div>
        </UCard>

        <UCard>
          <template #header>
            <b>Quantités et facturation</b>
          </template>
          <div class="flex flex-wrap items-end gap-4">
            <UFormField v-for="f in quantityFields" :key="f.key" :label="f.label">
              <UInputNumber v-model="offer.quantities[f.key]" :min="0" :max="9999" class="w-32" />
            </UFormField>
            <UFormField label="Période">
              <USelect v-model="offer.period" :items="[{ label: 'Mensuelle', value: 'monthly' }, { label: `Annuelle (${catalogue.pricing.yearlyFreeMonths} mois offerts)`, value: 'yearly' }]" class="w-56" />
            </UFormField>
          </div>
        </UCard>
      </div>

      <aside>
        <UCard class="md:sticky md:top-6">
          <template #header>
            <b>Votre devis</b>
          </template>
          <p v-if="quoteError" class="text-sm text-error">
            {{ quoteError }}
          </p>
          <table v-else-if="quote" class="w-full text-sm">
            <tbody>
              <tr v-for="l in quote.lines" :key="l.kind + l.code">
                <td>{{ l.name }} <span class="text-muted">× {{ l.quantity }}</span></td>
                <td class="text-right">
                  {{ euros(l.totalCents) }}
                </td>
              </tr>
              <tr v-if="quote.discountCents">
                <td>Remise volume {{ quote.discountPercent }} %</td>
                <td class="text-right">
                  − {{ euros(quote.discountCents) }}
                </td>
              </tr>
              <tr v-if="quote.minimumTopUpCents">
                <td>Complément minimum</td>
                <td class="text-right">
                  {{ euros(quote.minimumTopUpCents) }}
                </td>
              </tr>
              <tr>
                <td><b>Par mois (HT)</b></td>
                <td class="text-right">
                  <b>{{ euros(quote.monthlyCents) }}</b>
                </td>
              </tr>
              <tr v-if="quote.period === 'yearly'">
                <td>Par an</td>
                <td class="text-right">
                  <b>{{ euros(quote.periodTotalCents) }}</b>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-3 text-xs text-muted">
            Rien à payer pendant l’essai de {{ trialDays }} jours.
          </p>
          <template #footer>
            <UButton block label="Continuer" trailing-icon="i-lucide-arrow-right" :disabled="!quote" @click="step = 2" />
          </template>
        </UCard>
      </aside>
    </div>

    <!-- Step 2: company and owner -->
    <form v-else-if="step === 2" class="mx-auto grid max-w-xl gap-4" @submit.prevent="submit">
      <UCard>
        <template #header>
          <b>Votre entreprise</b>
        </template>
        <div class="grid gap-3">
          <UFormField label="Nom de l’entreprise ou de la conciergerie" required>
            <UInput v-model="company.name" class="w-full" autocomplete="organization" />
          </UFormField>
          <UFormField label="Identifiant" help="Utilisé dans vos adresses Rocket. Minuscules, chiffres et tirets.">
            <UInput v-model="company.slug" class="w-full" @input="slugTouched = true" />
          </UFormField>
          <div class="grid gap-3 sm:grid-cols-2">
            <UFormField label="Pays">
              <USelect v-model="company.country" :items="countries" class="w-full" />
            </UFormField>
            <UFormField label="N° de TVA (facultatif)">
              <UInput v-model="company.vatNumber" class="w-full" />
            </UFormField>
          </div>
        </div>
      </UCard>
      <UCard>
        <template #header>
          <b>Vous (propriétaire du compte)</b>
        </template>
        <div class="grid gap-3">
          <UFormField label="Nom" required>
            <UInput v-model="owner.name" class="w-full" autocomplete="name" />
          </UFormField>
          <UFormField label="E-mail" required>
            <UInput v-model="owner.email" type="email" class="w-full" autocomplete="email" />
          </UFormField>
          <URadioGroup
            v-model="owner.authProvider"
            :items="[{ label: 'Choisir un mot de passe', value: 'password' }, { label: 'Continuer avec Rocket Auth', value: 'rocket-auth', description: 'Bientôt : connexion unique à toute la suite.' }]"
          />
          <UFormField v-if="owner.authProvider === 'password'" label="Mot de passe" help="10 caractères minimum.">
            <UInput v-model="owner.password" type="password" class="w-full" autocomplete="new-password" />
          </UFormField>
          <!-- honeypot -->
          <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0">
          <UCheckbox v-model="acceptTerms" label="J’accepte les conditions d’utilisation et la politique de confidentialité." />
        </div>
      </UCard>
      <UAlert v-if="submitError" color="error" variant="subtle" :description="submitError" />
      <div class="flex justify-between">
        <UButton variant="ghost" icon="i-lucide-arrow-left" label="Offre" @click="step = 1" />
        <UButton type="submit" label="Créer mon compte" :loading="submitting" :disabled="!canSubmit" />
      </div>
    </form>

    <!-- Step 3: confirmation and next steps -->
    <div v-else-if="step === 3 && result" class="mx-auto grid max-w-xl gap-4">
      <UCard>
        <div class="text-center">
          <UIcon name="i-lucide-party-popper" class="size-10 text-primary" />
          <h2 class="mt-2 text-xl font-semibold">
            Bienvenue, {{ result.owner.name }} !
          </h2>
          <p class="mt-1 text-muted">
            Le compte <b>{{ result.account.name }}</b> est créé, en essai jusqu’au {{ dateFr(result.account.trialEndsAt) }}.
          </p>
        </div>
        <UAlert
          class="mt-4"
          color="info"
          variant="subtle"
          icon="i-lucide-mail"
          :title="`Confirmez votre adresse ${result.verification.email}`"
          :description="result.verification.sent ? 'Nous venons de vous envoyer un lien de confirmation.' : 'Le lien de confirmation va vous être envoyé.'"
        />
        <p v-if="result.verification.devUrl" class="mt-2 text-xs text-muted">
          Démo (aucun service d’e-mail configuré) : <NuxtLink :to="result.verification.devUrl.replace(/^https?:\/\/[^/]+/, '')" class="underline">confirmer maintenant</NuxtLink>
        </p>
      </UCard>
      <UCard>
        <template #header>
          <b>Prochaines étapes</b>
        </template>
        <ol class="grid gap-2 text-sm">
          <li v-for="(s, i) in result.nextSteps" :key="s.key" class="flex items-center gap-2">
            <UBadge :label="String(i + 1)" variant="subtle" />
            {{ s.label }}
          </li>
        </ol>
      </UCard>
    </div>
  </div>
</template>
