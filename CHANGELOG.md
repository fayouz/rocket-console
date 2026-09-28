# Changelog

Toutes les évolutions notables de Rocket Console. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [0.1.0] - 2026-09-28

### Ajouté
- Extraction du stock de Rocket Place, sur le modèle de Rocket Clean : lieu par identifiant (`placeId`), `Site` local ou cache de Rocket Place (`PlaceClient`/`PlaceDirectory`, mode suite par jeton Rocket Auth d'audience `rocket-place`).
- Catalogue `Item` (unité, catégorie consommable / linge / équipement, référence, seuil, quantité d'achat, coût unitaire, fournisseur, notes) à `/api/stock-items`, compatible avec Place.
- `Location` (lieu + emplacement « réserve », « cuisine »…) et `Level` (quantité + état OK / Bas / Vide, seuil et cible propres) à `/api/stock-levels` (compatible Place, `PATCH {"level"}`) et `/api/places/{placeId}/stock`.
- `Movement` : entrée, sortie, consommation, transfert, inventaire ; idempotent par `externalRef` ; origine host / pms / place / clean / stock + application ; usage location / perso ; coût au moment du mouvement. `POST /api/movements` (un ou une liste, tout ou rien).
- Magasins et fournisseurs (`Supplier` : type, adresse, coordonnées, horaires), offres par article (`ItemOffer` : magasin préféré, prix et taille de paquet).
- Liste de courses calculée (`/api/shopping-list`) et paniers (`/api/shopping-carts` : groupés par magasin, cochés en magasin, terminés en entrées de stock idempotentes `cart:<id>:<ligne>`, partage en texte).
- `Equipment` : série, achat, garantie, notice (référence Rocket Cloud).
- Export du bilan (`/api/export/movements`, `/api/export/consumption`, JSON ou CSV), filtré par usage.
- Alertes de stock bas par e-mail via Rocket Mailer, désactivées par défaut (`STOCK_ALERT_EMAILS`).
- Accès : `StockAccessVoter` (STOCK_READ / STOCK_MANAGE), `StockScopeGuardListener` (applications pour elles-mêmes).
- Tableau de bord (articles, à réassortir, consommé en location), données de démo (mêmes lieux que la démo de Place), interface (lieux, courses et panier mobile, mouvements, catalogue, magasins, équipements).
- Identité : `app_id` `stock`, jetons `rco_…`, ports front 4200 · api 9200 · docs 4201, base de dev `rocket-console-db` (127.0.0.1:55440).
