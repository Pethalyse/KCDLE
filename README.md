# KCDLE

KCDLE est un jeu quotidien où l’utilisateur doit deviner un joueur (univers LoL / Karmine Corp) à partir d’indices.
Le projet est split en 2 parties :

* **backend/** : API Laravel + admin Filament + scheduler (daily games, purge, PVP ticks…)
* **frontend/** : SPA Vue 3 (Vite + TypeScript)

Le but de ce README est que n’importe qui puisse cloner le repo, lancer l’app en local, comprendre l’architecture et contribuer sans friction.

---

## Sommaire

* [Stack & architecture](#stack--architecture)
* [Prérequis](#prérequis)
* [Installation rapide](#installation-rapide)
* [Développement](#développement)

  * [Démarrer Docker (API + DB + scheduler + nginx + adminer)](#démarrer-docker-api--db--scheduler--nginx--adminer)
  * [Installer les dépendances backend (Composer)](#installer-les-dépendances-backend-composer)
  * [Configurer l’environnement Laravel](#configurer-lenvironnement-laravel)
  * [Démarrer le frontend (Vue)](#démarrer-le-frontend-vue)
  * [URLs & ports](#urls--ports)
* [Commandes utiles](#commandes-utiles)
* [Tests](#tests)
* [Qualité / lint / format](#qualité--lint--format)
* [Scheduler & tâches planifiées](#scheduler--tâches-planifiées)
* [Base de données & seeders](#base-de-données--seeders)
* [Contribuer](#contribuer)
* [Troubleshooting](#troubleshooting)
* [Build / “prod” (optionnel)](#build--prod-optionnel)

---

## Stack & architecture

### Backend (Laravel)

* Laravel **12**
* PHP **8.2+** (les images Docker utilisent PHP **8.3**)
* PostgreSQL **16**
* Filament **v3** (admin)
* Sanctum (auth / sessions selon le contexte)

Le backend expose :

* `/api` : API consommée par le frontend
* `/admin` : interface Filament
* `/storage` : fichiers publics (images, etc.) via `storage:link`

### Frontend (Vue)

* Vue 3 + Vue Router + Pinia
* Vite + TypeScript
* L’API est configurable via `VITE_API_BASE_URL`

### Docker (dev)

Le `docker-compose.yml` (racine) lance :

* `db` (Postgres)
* `app` (php-fpm + Laravel)
* `scheduler` (php artisan schedule:work)
* `nginx` (reverse proxy HTTP vers php-fpm)
* `adminer` (UI DB)

⚠️ Important : en **dev**, `backend/` est monté en volume dans le container (`./backend:/var/www/html`), donc **il faut installer les dépendances Composer pour générer ************`backend/vendor`************ sur la machine hôte** (via une commande Docker fournie plus bas).

---

## Prérequis

### Obligatoires

* **Docker** + **Docker Compose**
* **Node.js** : `^20.19.0` ou `>= 22.12.0` (requis par le frontend)

### Recommandés (pour bosser hors Docker aussi)

* PHP 8.2+
* Composer

---

## Installation rapide

```bash
git clone https://github.com/Pethalyse/KCDLE.git
cd KCDLE
cp backend/.env.example backend/.env
```

Ensuite :

1. Ajuster `backend/.env` pour Docker (voir section dédiée)
2. Installer les dépendances backend (Composer) via Docker
3. Générer la clé Laravel
4. Démarrer les conteneurs
5. Lancer le frontend Vue

---

## Développement

### Démarrer Docker (API + DB + scheduler + nginx + adminer)

```bash
docker compose up -d --build
```

Si c’est un premier lancement et que l’app ne démarre pas encore, c’est normal tant que :

* `backend/vendor` n’existe pas
* `APP_KEY` n’est pas défini

Les étapes suivantes corrigent ça.

---

### Installer les dépendances backend (Composer)

Comme `backend/` est monté en volume, il faut créer `backend/vendor` sur la machine hôte.
Le plus simple est d’utiliser un “run” Docker en désactivant les migrations automatiques :

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app composer install
```

---

### Configurer l’environnement Laravel

#### 1) Variables backend minimales pour Docker

Dans `backend/.env`, assure-toi au minimum d’avoir :

```env
APP_ENV=dev
APP_DEBUG=true
APP_URL=http://localhost:8080

FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=kcdle
DB_USERNAME=kcdle
DB_PASSWORD=kcdle

SESSION_DRIVER=database
```

> Le `docker-compose.yml` configure Postgres avec `kcdle/kcdle`.

#### 2) Générer la clé APP_KEY

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app php artisan key:generate
```

#### 3) Migrations & seed

Par défaut, le container `app` lance automatiquement (via `docker/php/entrypoint.sh`) :

* `storage:link`
* `package:discover`
* caches (config/route/view)
* `php artisan migrate --force --seed`

Si tu veux relancer manuellement :

```bash
docker compose exec app php artisan migrate --seed
```

---

### Démarrer le frontend (Vue)

Le frontend est dans `frontend/`.

1. Créer un `.env` frontend (ou utiliser un export shell) :

```bash
cp frontend/.env.prod.example frontend/.env
```

Puis édite `frontend/.env` (en local) :

```env
VITE_ENV=dev
VITE_API_BASE_URL=http://localhost:8080/api

VITE_PUBLISHER_ID=
VITE_AD_SENSE_ID=
```

2. Installer / lancer :

```bash
cd frontend
npm install
npm run dev
```

---

### URLs & ports

* Frontend (Vite) : `http://localhost:5173`
* Backend (nginx -> php-fpm) : `http://localhost:8080`
* API : `http://localhost:8080/api`
* Admin Filament : `http://localhost:8080/admin`
* Adminer : `http://localhost:8081`
* Postgres (host) : `localhost:5433` (port exposé par compose)

---

## Commandes utiles

### Logs

```bash
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f scheduler
docker compose logs -f db
```

### Artisan (dans le container)

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
docker compose exec app php artisan tinker
```

### Regénérer les daily games (manuel)

```bash
docker compose exec app php artisan kcdle:generate-daily-games
```

### Reset DB

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Rebuild complet

```bash
docker compose down -v
docker compose up -d --build
```

---

## Tests

### Backend (Laravel)

Les tests utilisent SQLite en mémoire (config imposée par `backend/phpunit.xml`) :

* `DB_CONNECTION=sqlite`
* `DB_DATABASE=:memory:`

Lancer les tests via Docker :

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app php artisan test
```

Ou si tu bosses hors Docker (PHP + Composer installés) :

```bash
cd backend
composer install
php artisan test
```

⚙️ CI : les workflows GitHub lancent également les tests et peuvent produire un rapport de couverture HTML.

---

## Qualité / lint / format

### Backend (PHP)

* Pint (Laravel) :

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app ./vendor/bin/pint
```

### Frontend (Vue)

Depuis `frontend/` :

```bash
npm run type-check
npm run lint
npm run format
```

---

## Scheduler & tâches planifiées

Le service `scheduler` lance :

```bash
php artisan schedule:work
```

Les tâches sont définies dans `backend/routes/console.php`, notamment :

* `kcdle:generate-daily-games` (tous les jours à 00:00)
* `kcdle:purge-pending-guesses` (tous les jours à 00:00)
* `pvp:afk-sweep` (chaque minute)
* `pvp:passive-tick` (chaque seconde, `withoutOverlapping()`)

En dev, le scheduler tourne automatiquement via Docker.

---

## Base de données & seeders

Au démarrage (si `RUN_MIGRATIONS=true`), l’entrypoint exécute :

```bash
php artisan migrate --force --seed
```

Le seeding principal est dans `backend/database/seeders/DatabaseSeeder.php` et appelle notamment :

* Countries
* Roles
* Games
* Teams
* Leagues

---

## Contribuer

### Règles simples

* Une PR = une intention claire (feature / fix / refactor ciblé)
* Ajouter/adapter les **tests** quand c’est pertinent
* Éviter les changements “massifs” non liés (formatage global, renommages inutiles, etc.)

### Workflow recommandé

1. Créer une branche
2. Développer en local (Docker + frontend)
3. Lancer :

  * `php artisan test`
  * `pint` (si modif PHP)
  * `npm run lint` / `type-check` (si modif frontend)
4. Ouvrir une PR

---

## Troubleshooting

### “vendor/autoload.php not found”

Tu n’as pas encore installé Composer dans `backend/`.

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app composer install
```

### “No application encryption key has been specified”

`APP_KEY` est vide.

```bash
docker compose run --rm -e RUN_MIGRATIONS=false app php artisan key:generate
```

### L’API répond mais le frontend n’affiche rien / erreurs réseau

Vérifie `frontend/.env` :

* `VITE_API_BASE_URL=http://localhost:8080/api`

Puis relance `npm run dev`.

### Problèmes de cache Laravel après modifs

```bash
docker compose exec app php artisan optimize:clear
```

### Reset total (DB incluse)

```bash
docker compose down -v
docker compose up -d --build
```

---

## Build / “prod” (optionnel)

Le repo contient aussi :

* `docker-compose.beta.yml`
* `docker-compose.prod.yml`

Ils sont surtout pensés pour l’infra actuelle (images GHCR + Caddy + config nginx prod).
Si ton objectif est uniquement de contribuer, tu peux ignorer cette section.

### Ce que ça change

* Le **frontend** est buildé et copié dans `backend/public/` lors du build de l’image (`docker/php/Dockerfile.prod`)
* `nginx` sert la SPA et ne renvoie vers Laravel que pour `/api`, `/admin`, etc.
* Un volume `storage_data` est utilisé pour persister `storage/app/public`
