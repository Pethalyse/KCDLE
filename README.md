# KCDLE

KCDLE est un jeu quotidien où l'utilisateur doit deviner un joueur de la Karmine Corp à partir d'indices. L'application repose sur une architecture Laravel, Vue, PostgreSQL et Docker, avec un comportement différent en développement et en production.

---

# Développement

## 1. Prérequis

* Docker et Docker Compose
* Node.js 20+
* Git

## 2. Installation

```bash
git clone https://github.com/Pethalyse/KCDLE.git
cd KCDLE
cp backend/.env.example backend/.env
```

Configurer `backend/.env` selon votre environnement.

## 3. Lancer l'environnement de développement

```bash
docker compose -f docker-compose.yml up -d --build
```

Ce compose lance uniquement l'API Laravel et PostgreSQL.

## 4. Lancer le frontend

```bash
cd frontend
npm install
npm run dev
```

## 5. Accès en développement

* Frontend : [http://localhost:5173](http://localhost:5173)
* API : [http://localhost:8080/api](http://localhost:8080/api)
* Admin Filament : [http://localhost:8080/admin](http://localhost:8080/admin)

## 6. Commandes utiles

```bash
docker compose -f docker-compose.yml exec app php artisan migrate
docker compose -f docker-compose.yml exec app php artisan optimize:clear
docker compose -f docker-compose.yml exec app php artisan kcdle:generate-daily-games
```

---

# Production

## 1. Architecture

En production, l'application utilise trois blocs principaux :

* nginx sert le frontend ainsi que les routes API et admin
* php-fpm exécute Laravel
* Caddy agit comme reverse proxy public et gère automatiquement les certificats HTTPS

## 2. Installation

```bash
git clone https://github.com/Pethalyse/KCDLE.git
cd KCDLE
cp backend/.env.production.example backend/.env
```

Configurer `backend/.env` avec les valeurs de production (domaine, base de données, clés, mail, etc.).

## 3. Déploiement

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Tout est automatisé : migrations, caches Laravel, permissions, scheduler, génération du frontend, HTTPS.

## 4. Accès en production

* Frontend : https://ton_domaine/
* API : https://ton_domaine/api
* Admin Filament : https://ton_domaine/admin

## 5. DNS requis

A records ou équivalent :

```
ton_domaine → IP du VPS
www.ton_domaine → ton_domaine
```

Caddy gère automatiquement la génération et le renouvellement des certificats SSL.

## 6. Commandes utiles

```bash
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan kcdle:generate-daily-games
docker compose -f docker-compose.prod.yml logs -f app
```

---

# Structure du projet

```
backend/                Laravel
frontend/               Vue 3
docker/
  php/                  Dockerfile + entrypoint
  nginx/                Configuration nginx
  caddy/                Fichier Caddy (reverse proxy)

docker-compose.dev.yml
docker-compose.prod.yml
```

---

# Commandes artisan principales

```bash
php artisan kcdle:generate-daily-games
php artisan migrate --force
php artisan optimize:clear
php artisan make:filament-user
php artisan tinker
>>> use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'name' => 'Admin',
    'email' => 'admin@kcdle.test',
    'password' => Hash::make('motdepasse-super-secret'),
]);
```

---

# Sécurité

* HTTPS obligatoire en production via Caddy
* APP_DEBUG=false en production
* Rate limiting configuré
* Permissions Laravel fixées automatiquement au lancement

---

# Monitoring

Route de contrôle recommandée :

```
GET /api/health
```

Répond avec un statut indiquant l'état de l'application et de la base de données.
