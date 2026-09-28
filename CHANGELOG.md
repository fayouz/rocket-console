# Changelog

Toutes les évolutions notables de Rocket Console. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [0.1.0] - 2026-09-28

### Ajouté
- Squelette sur la stack des briques Rocket (Symfony 8.1 + rocket/core-bundle, Nuxt 4 + @rocket/core). Identité : `app_id` `console`, jetons `rco_…`, ports front 4200 · api 9200 · docs 4201, base de dev `rocket-console-db` (127.0.0.1:55440).
- Catalogue éditable : briques (famille, dépendances, unité, prix), options (brique, brique activée), offres (Rocket Host 10 €/logement, Rocket Location 22 €/logement), règles de prix (minimum 29 €/mois, −15 % dès 10, −25 % dès 30, 2 mois offerts à l'année, essai 30 jours) ; valeurs par défaut via `console:catalogue:seed`.
- Comptes clients (statut essai/actif/suspendu/clôturé, facturation, pays, TVA) et membres (propriétaire/admin/membre).
- Abonnements (offre, briques à la carte, options, quantités, mensuel/annuel) validés contre le catalogue (dépendances), devis `QuoteCalculator` et `POST /api/quote`.
- Droits `GET /api/entitlements/{compte}` (jeton d'application), document signé JWS HS256/EdDSA (`ROCKET_CONSOLE_SIGNING_KEY`) servant de clé de licence : `GET /api/accounts/{compte}/licence`, `console:licence:issue`, `POST /api/licences/verify`, `GET /api/licences/public-key`.
- Tableau de bord (MRR estimé, comptes actifs, essais qui se terminent), page Revenus, journal d'audit de toutes les modifications.
- Démo : compte « loussahousing » (Rocket Location, 2 logements) et essai « gite-des-oliviers ».
