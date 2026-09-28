# Changelog

Toutes les évolutions notables de Rocket Console. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Non publié]

### Modifié
- **Secrets des intégrations dans le coffre de rocket-core** (0.3.1, Administration → Secrets) : `ROCKET_CONSOLE_SIGNING_KEY` → `rocket.console.signing_key` ; lus à l'exécution par `App\Secrets\IntegrationSecrets`, jamais renvoyés par l'API. Repli temporaire sur l'ancienne variable (avertissement « deprecated »). Seule `ROCKET_SECRETS_KEY` (clé maîtresse) reste dans l'environnement.

### Ajouté
- Commande `app:secrets:migrate-env [--dry-run] [--overwrite]` : importe les anciennes variables dans le coffre, idempotente.

## [0.2.1] - 2026-09-28

### Modifié
- CI : images Docker publiées sur ghcr.io uniquement sur tag `v*` et lancement manuel, multi-arch amd64/arm64, SBOM et provenance (workflow `docker-images.yml` de rocket-core) ; image API sur FrankenPHP Alpine sans Composer, `HEALTHCHECK` API et front ; exemple `compose.prod.yaml`.

## [0.2.0] - 2026-09-28

### Ajouté
- **Inscription en libre-service** avec choix de l'offre : page publique `/inscription` (offre ou briques à la carte, options, quantités, mensuel/annuel, devis en direct ; entreprise et propriétaire ; confirmation et prochaines étapes) et `/inscription/verifier`.
- API publique limitée par IP : `GET /api/public/catalogue`, `POST /api/public/quote`, `POST /api/public/signup` (champ piège, délai minimal, idempotente par e-mail non vérifié), `POST /api/public/verify-email`.
- Compte créé en essai (`trialDays`), abonnement créé, propriétaire membre `owner` en attente de vérification ; e-mail par Rocket Mailer si `ROCKET_MAILER_URL`, sinon lien journalisé (et renvoyé hors production).
- Opérateur : badge « Nouveau » et filtre « Nouvelles inscriptions » (`GET /api/accounts?new=1`), `POST /api/accounts/{slug}/signup-reviewed`, badge « E-mail non vérifié » sur les membres.

### Modifié
- Membres : mot de passe (haché) et vérification d'e-mail ; les membres existants sont marqués vérifiés par la migration.

## [0.1.0] - 2026-09-28

### Ajouté
- Squelette sur la stack des briques Rocket (Symfony 8.1 + rocket/core-bundle, Nuxt 4 + @rocket/core). Identité : `app_id` `console`, jetons `rco_…`, ports front 4200 · api 9200 · docs 4201, base de dev `rocket-console-db` (127.0.0.1:55440).
- Catalogue éditable : briques (famille, dépendances, unité, prix), options (brique, brique activée), offres (Rocket Host 10 €/logement, Rocket Location 22 €/logement), règles de prix (minimum 29 €/mois, −15 % dès 10, −25 % dès 30, 2 mois offerts à l'année, essai 30 jours) ; valeurs par défaut via `console:catalogue:seed`.
- Comptes clients (statut essai/actif/suspendu/clôturé, facturation, pays, TVA) et membres (propriétaire/admin/membre).
- Abonnements (offre, briques à la carte, options, quantités, mensuel/annuel) validés contre le catalogue (dépendances), devis `QuoteCalculator` et `POST /api/quote`.
- Droits `GET /api/entitlements/{compte}` (jeton d'application), document signé JWS HS256/EdDSA (`ROCKET_CONSOLE_SIGNING_KEY`) servant de clé de licence : `GET /api/accounts/{compte}/licence`, `console:licence:issue`, `POST /api/licences/verify`, `GET /api/licences/public-key`.
- Tableau de bord (MRR estimé, comptes actifs, essais qui se terminent), page Revenus, journal d'audit de toutes les modifications.
- Démo : compte « loussahousing » (Rocket Location, 2 logements) et essai « gite-des-oliviers ».
