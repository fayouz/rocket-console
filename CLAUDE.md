# Rocket Console

**Stock** des lieux, extrait de Rocket Place, sur la stack des briques Rocket (même schéma que Rocket Clean). Socle commun : [rocket-core](https://github.com/fayouz/rocket-core) (bundle Symfony `rocket/core-bundle` + layer Nuxt `@rocket/core`), à lire avant de modifier les comptes, le SSO, les applications, le tableau de bord ou la mise en page : ce code n'est pas ici.

## Repères
- `app_id` `stock`, jetons d'application `rco_…`, ports front 4200 · api 9200 · docs 4201. Base de dev : conteneur `rocket-console-db` (postgres:16-alpine, 127.0.0.1:55440, app/app), `.env.local` / `.env.test.local` non suivis.
- Domaine : `Item` (table `stock_item`, catalogue), `Supplier` (magasin `store` ou fournisseur), `ItemOffer` (article × magasin : préféré, prix du paquet, taille du paquet), `Location` (`placeId` + emplacement, jamais de clé étrangère vers un lieu), `Level` (table `stock_level` : quantité + état ok/low/empty, `thresholdOverride`, `targetQuantity`), `Movement` (table `stock_movement`, jamais modifié, `externalRef` unique, `usage` rental/personal, `origin`), `Equipment`, `ShoppingCart` + `CartLine`, `Site` (lieu local ou cache de Rocket Place).
- `Stock/StockBook` (emplacements et niveaux trouvés ou créés, application des mouvements, alertes), `Stock/Replenishment` (besoins : niveaux sous le seuil, quantité jusqu'à la cible arrondie au paquet de l'offre préférée), `Stock/StockAlerter` (Rocket Mailer, seulement si `STOCK_ALERT_EMAILS`), `Stock/Payload` (lecture des corps JSON, erreurs 422 en français).
- Lieux : `Place/PlaceDirectory` → `Place/PlaceClient` (Rocket Place, `ROCKET_PLACE_URL`/`ROCKET_PLACE_TOKEN`, mode suite par `ServiceTokenProvider` audience `rocket-place`) si configuré, sinon `Site` locaux. E-mails : `Mailer/MailerClient` (+ `DemoMailer`).
- Contrôleurs sans API Platform (JSON à la main) : `ItemController` et `LevelController` gardent les chemins et champs de Rocket Place (`/api/stock-items`, `/api/stock-levels`, IRI `place`/`item`, `PATCH {"level"}`) ; `MovementController`, `ShoppingListController`, `ShoppingCartController`, `SupplierController`, `EquipmentController`, `ExportController`, `PlaceController`. Accès : `Security/StockAccessVoter` (STOCK_READ tout utilisateur, STOCK_MANAGE admin ; applications pour elles-mêmes : tout), `Security/StockScopeGuardListener`. Tableau de bord : `Dashboard/StockSection`. Démo : `Command/StockDemoSeeder`.
- Front : `pages/places/[id].vue` (`StockTab`), `pages/courses/index.vue` + `[id].vue` (panier mobile), `pages/mouvements.vue`, `pages/catalogue.vue`, `pages/magasins.vue`, `pages/equipements.vue`.

## Vérifier avant de pousser
```bash
cd backend && php bin/console lint:container && php bin/console doctrine:schema:validate && php bin/phpunit
cd frontend && npm run lint && npm run typecheck
cd docs && npm run lint && npm run typecheck && npm run generate   # si docs/ a changé
```

## Pièges connus
- Les tests restent hors réseau : `ROCKET_PLACE_URL` vide (autonome), `DemoMailer`, `STOCK_ALERT_EMAILS` vide ; le mode Place est couvert par `tests/Unit/PlaceDirectoryTest` (MockHttpClient).
- `StockBook::location()` flush à la création d'un emplacement : `POST /api/movements` s'exécute dans une transaction explicite (tout ou rien).
- Colonne `usage_kind` (propriété `Movement::$usage`).
- Migrations : lancer d'abord celles du socle, puis `doctrine:migrations:diff`.
- Pas de Composer sur le Mac de Faez : `docker run -d -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs` puis `docker wait`. Cache npm global en erreur de droits : `npm ci --cache <dossier temporaire>`.
