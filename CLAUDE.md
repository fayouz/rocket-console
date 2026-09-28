# Rocket Console

**Console d'exploitation SaaS** de la suite Rocket : une instance partagée, multi-comptes (chaque client = un compte, futur organisation Rocket Auth), briques vendues à la carte. Source de vérité du catalogue, des abonnements et des **droits** (entitlements) des comptes. Socle commun : [rocket-core](https://github.com/fayouz/rocket-core) (bundle `rocket/core-bundle` + layer Nuxt `@rocket/core`), à lire avant de toucher comptes opérateurs, SSO, applications, tableau de bord ou mise en page.

## Repères
- `app_id` `console`, jetons d'application `rco_…`, ports front 4200 · api 9200 · docs 4201. Base de dev : conteneur `rocket-console-db` (postgres:16-alpine, 127.0.0.1:55440, app/app), `.env.local` / `.env.test.local` non suivis.
- Catalogue : `Brick` (table `catalogue_brick` : code, famille middleware/business, `depends` [codes], unité per_property/per_place/per_screen/per_mailbox/flat, `monthlyPriceCents`), `Option` (`catalogue_option` : brique, `grants` = brique activée), `Plan` (`catalogue_plan` : briques incluses, prix unitaire), `PricingRules` (`catalogue_pricing`, ligne unique id 1 : minimum, paliers volume, mois offerts, jours d'essai). Défauts : `Billing/CatalogueSeeder` (constantes, jamais d'écrasement, `console:catalogue:seed`).
- Clients : `Account` (slug unique, statut trial/active/suspended/closed, facturation, pays, TVA), `Member` (email, rôle owner/admin/member), `Subscription` (plan?, bricks, options, quantities JSON, period, startsAt/endsAt ; `Account::currentSubscription()`), `AuditLog` (append-only, `Audit/AuditLogger::log()` persiste avec le flush de l'appelant).
- `Billing/` : `Catalogue` (instantané sans base, testable), `CatalogueProvider` (depuis la base), `SubscriptionDraft`, `SubscriptionRules::enabledBricks()` (codes connus/actifs, option sur brique active, dépendances ; une brique activée seulement par option est couverte par sa brique parente), `QuoteCalculator` (lignes → remise volume sur max(logements, lieux) → minimum si > 0 → annuel 12 − mois offerts ; `monthlyEquivalentCents` = MRR), `InvalidSubscription` (messages FR → 422).
- `Licence/` : `Entitlements::compute()` / `licence()` (briques, options, quotas, échéance : fin d'essai sinon +35 j / +380 j bornée par endsAt ; suspendu/clos/essai échu → rien), `LicenceSigner` (JWS compact HS256 ou EdDSA selon `ROCKET_CONSOLE_SIGNING_KEY` = `ed25519:<base64 seed>`).
- Contrôleurs JSON à la main (pas d'API Platform) : `CatalogueController`, `AccountController` (comptes, membres, abonnements, `/api/quote`, licence), `EntitlementController` (`/api/entitlements/{slug}`, `/api/licences/verify|public-key` : applications ou admin), `OverviewController` (`/api/overview`, `/api/audit-logs`). Opérateur = `ROLE_ADMIN`. `Security/ConsoleScopeGuardListener` ouvre aux applications agissant pour elles-mêmes seulement entitlements, licences et `GET /api/catalogue`. Tableau de bord : `Dashboard/ConsoleSection`. Démo : `Command/ConsoleDemoSeeder`. Commande : `console:licence:issue <compte> [--claims]`. `Support/Payload` : corps JSON, 422 en français.
- Inscription en libre-service : `Controller/PublicController` (`/api/public/{catalogue,quote,signup,verify-email}`, `PUBLIC_ACCESS`), `Signup/RateLimiter` (cache, par IP), `Signup/VerificationMailer` (Rocket Mailer si `ROCKET_MAILER_URL`, sinon journal), `Billing/SubscriptionPayload` (corps d'abonnement partagé). Pages publiques `pages/inscription/` (`publicPaths`). `Account.source = signup` + badge « Nouveau » jusqu'à `signup-reviewed`.
- Front : `pages/accounts/index.vue`, `pages/accounts/[slug].vue` (devis en direct, droits, licence), `pages/catalogue.vue`, `pages/revenus.vue`, `pages/journal.vue` ; types `types/console.ts`, utilitaires `utils/console.ts`.

## Vérifier avant de pousser
```bash
cd backend && php bin/console lint:container && php bin/console doctrine:schema:validate && php bin/phpunit
cd frontend && npm run lint && npm run typecheck
cd docs && npm run lint && npm run typecheck && npm run generate   # si docs/ a changé
```

## Pièges connus
- Tests hors réseau (`HttpMock`), clé de signature de test dans `.env.test`.
- Requêtes SQL sur les colonnes JSON : `jsonb_exists(col::jsonb, :c)` (l'opérateur `?` est pris pour un paramètre par DBAL).
- Migrations : lancer d'abord celles du socle, puis `doctrine:migrations:diff`.
- Pas de paiement : Stripe (l'inscription crée un essai sans carte), synchronisation des organisations Rocket Auth et lecteur d'entitlements dans rocket-core restent à faire.
- Pas de Composer sur le Mac de Faez : `docker run -d -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs` puis `docker wait`. npm : `--cache "$(mktemp -d)"`.
