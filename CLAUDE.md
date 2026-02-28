# CLAUDE.md — Search Console Dashboard

## Vue d'ensemble

**Search Console Dashboard** est une application complète de synchronisation et visualisation des données Google Search Console. Elle intègre un flux OAuth2 Google, une synchronisation CLI/cron des données de performance, et un dashboard interactif avec filtres, comparaisons de périodes et exports.

---

## Architecture

Application **MVC PHP** avec namespace PSR-4 `App\`, base MySQL, et dashboard JavaScript.

```
search-console/
├── composer.json           # Dépendances (google/apiclient, phpdotenv)
├── .env                    # Configuration (OAuth, DB, timezone)
├── config/
│   └── database.php        # Configuration PDO MySQL
├── src/
│   ├── Auth/
│   │   └── GoogleOAuth.php        # Gestion tokens OAuth2 Google
│   ├── Controller/
│   │   ├── ApiController.php      # Endpoints JSON (/api/*)
│   │   ├── AuthController.php     # Flux OAuth (login, callback, logout)
│   │   ├── DashboardController.php # Rendu dashboard HTML
│   │   └── SyncController.php     # Déclenchement sync
│   ├── Database/
│   │   └── Connection.php         # Singleton PDO
│   ├── Model/
│   │   ├── PerformanceData.php    # Requêtes données de performance
│   │   ├── Site.php               # Gestion des sites GSC
│   │   ├── SyncJob.php            # Jobs de synchronisation
│   │   └── SyncLog.php            # Journal des syncs
│   └── Service/
│       └── SearchConsoleAPI.php   # Client API Search Console
├── bin/
│   └── sync.php            # Script CLI de synchronisation
├── cron/
│   └── sync.sh             # Script cron pour sync automatique
├── database/
│   ├── schema.sql           # Schéma MySQL complet
│   ├── migration_sync_progress.sql
│   └── migration_reliable_sync.sql
├── public/
│   ├── index.php            # Front controller (standalone)
│   ├── .htaccess            # Réécriture Apache
│   └── assets/
│       ├── css/app.css      # Styles dashboard
│       └── js/dashboard.js  # JavaScript dashboard interactif
├── templates/
│   ├── layout.php           # Layout HTML de base
│   ├── dashboard.php        # Template dashboard
│   ├── auth.php             # Template page d'auth
│   └── sync-status.php      # Template statut de sync
├── storage/                 # Logs de sync
├── module.json              # Métadonnées plugin plateforme
├── boot.php                 # Bootstrap plateforme (autoloader + env)
└── adapter.php              # Routeur interne (pont plateforme → controllers)
```

### Flux de données

1. **Auth** : OAuth2 Google → tokens stockés en MySQL (`oauth_tokens`)
2. **Sync** : CLI (`bin/sync.php`) ou API (`POST /api/sync`) → appel API GSC → INSERT MySQL
3. **Dashboard** : JS fetch vers `/api/*` → données agrégées depuis `performance_data`
4. **Affichage** : Chart.js pour les graphiques, tableaux triables pour le détail

---

## Base de données MySQL

### Tables

| Table | Description |
|-------|------------|
| `sites` | Sites GSC enregistrés (url, label, active) |
| `oauth_tokens` | Tokens OAuth2 (access_token, refresh_token, expires_at) |
| `performance_data` | Données de performance (date, page, query, country, device, clicks, impressions, ctr, position) |
| `sync_logs` | Journal de synchronisation (status, rows_fetched, duration) |

### Clé unique performance_data

`(site_id, data_date, search_type, country, device, query(255), page(255))` — déduplique les re-syncs.

---

## API Endpoints

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| GET | `/auth` | AuthController::index | Page de connexion OAuth |
| GET | `/auth/login` | AuthController::login | Redirection vers Google |
| GET | `/auth/callback` | AuthController::callback | Retour OAuth, stockage token |
| GET | `/auth/logout` | AuthController::logout | Déconnexion |
| GET | `/api/sites` | ApiController::sites | Liste des sites |
| GET | `/api/daily-trend` | ApiController::dailyTrend | Tendance quotidienne |
| GET | `/api/top-queries` | ApiController::topQueries | Top requêtes par clicks |
| GET | `/api/top-pages` | ApiController::topPages | Top pages par impressions |
| GET | `/api/devices` | ApiController::devices | Répartition par appareil |
| GET | `/api/countries` | ApiController::countries | Répartition par pays |
| GET | `/api/totals` | ApiController::totals | Totaux agrégés |
| GET | `/api/compare` | ApiController::compare | Comparaison deux périodes |
| GET | `/api/sync-logs` | ApiController::syncLogs | Journal des syncs |
| POST | `/api/sync` | ApiController::triggerSync | Déclencher une sync |
| GET | `/dashboard` | DashboardController::index | Dashboard principal |
| GET | `/sync-status` | DashboardController::syncStatus | Statut de sync |

---

## Intégration plateforme

- **Display mode** : `passthrough` — l'application gère son propre routage complet
- **Quota** : `none` — pas de limitation d'usage
- **Env keys** : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- **entry_point** : `adapter.php` (pas index.php)

### `boot.php`

- Charge `vendor/autoload.php`
- Définit `MODULE_URL_PREFIX` = `/m/search-console`
- Configure le timezone (`APP_TIMEZONE` ou `Europe/Paris`)

### `adapter.php`

- Routeur interne qui traduit les URLs plateforme (`/m/search-console/...`) vers les controllers
- Parse `$_SERVER['REQUEST_URI']`, strip le préfixe, dispatch via `if/elseif`
- Gestion d'erreurs avec affichage détaillé en dev, message générique en prod

---

## Dépendances

### Composer (`composer.json`)

| Package | Version | Usage |
|---------|---------|-------|
| `google/apiclient` | ^2.15 | SDK Google (OAuth2 + Search Console API) |
| `vlucas/phpdotenv` | ^5.6 | Chargement .env |
| PHP | >=8.1 | Requis |

### Autoload PSR-4

```
App\ → src/
```

---

## CLI & Cron

### Synchronisation

```bash
# Manuel
php bin/sync.php

# Via Composer
composer sync

# Cron automatique
bash cron/sync.sh
```

### Développement local

```bash
composer serve   # php -S localhost:8080 -t public
```

---

## Conventions

- Namespace **PSR-4** : `App\Controller`, `App\Model`, `App\Auth`, `App\Service`, `App\Database`
- Code en **français** (commentaires, messages d'erreur) sauf noms de namespace/classe (anglais historique)
- Réponses API en JSON (`ApiController::json()`)
- Templates PHP dans `templates/`
- Configuration via `.env` + `config/database.php`
