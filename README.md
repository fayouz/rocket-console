# Rocket Console

**Console d'exploitation SaaS** de la suite Rocket : une instance partagée pour tous les clients, briques vendues à la carte. Catalogue (briques, options, offres, règles de prix), comptes clients et membres, abonnements avec devis, **droits** (entitlements) lus par les briques et **clés de licence** signées pour les instances auto-hébergées. Tableau de bord MRR et journal d'audit. Pas encore de paiement.

Stack : Symfony 8.1 + [rocket/core-bundle](https://github.com/fayouz/rocket-core), Nuxt 4 + layer `@rocket/core`, PostgreSQL 16.

## Démarrer

```bash
docker compose up -d --build          # front http://localhost:4200, API http://localhost:9200/api/docs
docker compose -f compose.yaml -f compose.demo.yaml up -d --build   # démo (voir demo/README.md)
```

Développement :

```bash
docker run -d --name rocket-console-db -e POSTGRES_USER=app -e POSTGRES_PASSWORD=app -e POSTGRES_DB=app -p 127.0.0.1:55440:5432 postgres:16-alpine
cd backend
printf 'DATABASE_URL="postgresql://app:app@127.0.0.1:55440/app?serverVersion=16&charset=utf8"\nMESSENGER_TRANSPORT_DSN=sync://\n' > .env.local && cp .env.local .env.test.local
php bin/console lexik:jwt:generate-keypair && php bin/console doctrine:migrations:migrate -n
DEMO_MODE=1 php bin/console app:demo:seed
php -S 127.0.0.1:9200 -t public
cd ../frontend && npm install && NUXT_PUBLIC_API_BASE=http://localhost:9200 npm run dev -- --port 4200
```

## Configuration

| Variable | Rôle |
|---|---|
| `ROCKET_CONSOLE_SIGNING_KEY` | Clé de signature des droits et licences : `ed25519:<32 octets base64>` (EdDSA, clé publique sur `/api/licences/public-key`) ou secret ≥ 32 caractères (HS256). Vide : pas de document signé, licences en 503. |
| `ROCKET_AUTH_URL`, `ROCKET_AUTH_INTERNAL_URL`, `ROCKET_AUTH_CLIENT_ID` (`rocket-console`), `ROCKET_AUTH_CLIENT_SECRET`, `ROCKET_AUTH_ADMIN_GROUP` | Mode suite (connexion par Rocket Auth). Vide : autonome. |
| Socle | `APP_SECRET`, `DATABASE_URL`, `JWT_PASSPHRASE`, `SETUP_TOKEN`, `SECRETS_ENCRYPTION_KEY`, `LDAP_*`, `UPDATE_*` : voir rocket-core. |

## API

Voir [docs/content/3.api/2.domain.md](docs/content/3.api/2.domain.md). Pour une brique : `GET /api/entitlements/{compte}` avec `Authorization: Bearer rco_…` (application créée dans Administration → Applications).

Licence en ligne de commande : `php bin/console console:licence:issue loussahousing --claims`.

## Tests

```bash
cd backend && php bin/console lint:container && php bin/console doctrine:schema:validate && php bin/phpunit
cd frontend && npm run lint && npm run typecheck
```
