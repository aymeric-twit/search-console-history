# Google Search Console — Dashboard & Data Sync

Application PHP qui synchronise automatiquement les donnees de performance Google Search Console dans une base MySQL et expose un tableau de bord analytique.

## Fonctionnalites

- **OAuth2 Google** avec refresh automatique des tokens
- **Synchronisation quotidienne** des donnees de performance (clicks, impressions, CTR, position)
- **5 dimensions** : date, page, requete, pays, appareil
- **3 types de recherche** : web, image, video
- **Dashboard interactif** avec graphiques Chart.js
- **API JSON** pour integration avec des outils externes
- **Gestion de la pagination** (25 000 lignes max par requete API)
- **Deduplication** automatique via UPSERT
- **Retry automatique** avec back-off exponentiel en cas d'erreur API
- **Historisation complete** (les donnees GSC ne sont disponibles que ~16 mois)

## Prerequis

- PHP >= 8.1 avec extensions `pdo_mysql`, `curl`, `json`
- MySQL >= 5.7 ou MariaDB >= 10.3
- Composer
- Un projet Google Cloud avec l'API Search Console activee

## Installation

### 1. Cloner et installer les dependances

```bash
cd search-console
composer install
```

### 2. Creer les credentials Google Cloud

1. Aller sur [Google Cloud Console](https://console.cloud.google.com/)
2. Creer un nouveau projet (ou en selectionner un existant)
3. Activer l'**API Google Search Console** :
   - Menu > APIs & Services > Library
   - Chercher "Google Search Console API"
   - Cliquer sur "Enable"
4. Creer des identifiants OAuth 2.0 :
   - Menu > APIs & Services > Credentials
   - Cliquer "Create Credentials" > "OAuth client ID"
   - Type : "Web application"
   - Nom : "Search Console Dashboard"
   - URIs de redirection autorises : `http://localhost:8080/auth/callback`
   - Copier le **Client ID** et le **Client Secret**
5. Configurer l'ecran de consentement OAuth :
   - Menu > APIs & Services > OAuth consent screen
   - Ajouter les scopes necessaires
   - Ajouter votre email comme utilisateur test (si en mode "Testing")

### 3. Configurer l'application

```bash
cp .env.example .env
```

Editer `.env` avec vos credentials :

```env
GOOGLE_CLIENT_ID=123456789.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxx
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/callback

DB_HOST=127.0.0.1
DB_NAME=search_console
DB_USER=root
DB_PASSWORD=motdepasse
```

### 4. Creer la base de donnees

```bash
mysql -u root -p < database/schema.sql
```

### 5. Lancer l'application

```bash
# Serveur de developpement PHP
composer serve
# ou
php -S localhost:8080 -t public
```

Ouvrir `http://localhost:8080` dans le navigateur et cliquer sur "Se connecter avec Google".

## Synchronisation des donnees

### Manuellement

```bash
# Synchroniser tous les sites
php bin/sync.php

# Synchroniser un site specifique
php bin/sync.php --site-id=1

# Sans re-importer la liste des sites
php bin/sync.php --no-import
```

### Via le dashboard

Aller sur `/sync-status` et cliquer sur "Lancer une synchronisation maintenant".

### Automatiquement (cron)

Ajouter dans votre crontab (`crontab -e`) :

```cron
# Synchronisation quotidienne a 6h00
0 6 * * * /chemin/vers/search-console/cron/sync.sh
```

Ou directement :

```cron
0 6 * * * /usr/bin/php /chemin/vers/search-console/bin/sync.php >> /chemin/vers/search-console/storage/sync.log 2>&1
```

## API JSON

Tous les endpoints acceptent les parametres `site_id`, `from`, `to` et les filtres optionnels (`device`, `country`, `search_type`, `filter_query`, `filter_page`).

| Methode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/sites` | Liste des sites |
| GET | `/api/daily-trend` | Tendance quotidienne |
| GET | `/api/top-queries` | Top requetes par clicks |
| GET | `/api/top-pages` | Top pages par impressions |
| GET | `/api/devices` | Repartition par appareil |
| GET | `/api/countries` | Repartition par pays |
| GET | `/api/totals` | Totaux agreges |
| GET | `/api/compare` | Comparaison entre 2 periodes |
| GET | `/api/sync-logs` | Historique des syncs |
| POST | `/api/sync` | Declencher une sync |

### Exemple

```bash
curl "http://localhost:8080/api/top-queries?site_id=1&from=2025-01-01&to=2025-01-31&limit=10"
```

## Structure du projet

```
search-console/
├── bin/
│   └── sync.php              # Script CLI de synchronisation
├── config/
│   └── database.php           # Configuration PDO
├── cron/
│   └── sync.sh                # Script shell pour crontab
├── database/
│   └── schema.sql             # Schema MySQL + requetes exemples
├── public/
│   ├── index.php              # Front controller (routage)
│   ├── .htaccess              # Rewrite Apache
│   └── assets/
│       ├── css/app.css        # Styles du dashboard
│       └── js/dashboard.js    # Graphiques Chart.js
├── src/
│   ├── Auth/
│   │   └── GoogleOAuth.php    # OAuth2 Google + refresh auto
│   ├── Controller/
│   │   ├── ApiController.php      # Endpoints JSON
│   │   ├── AuthController.php     # Routes auth OAuth
│   │   ├── DashboardController.php # Interface web
│   │   └── SyncController.php     # Orchestration sync
│   ├── Database/
│   │   └── Connection.php     # Singleton PDO
│   ├── Model/
│   │   ├── PerformanceData.php # CRUD + requetes analytiques
│   │   ├── Site.php            # Gestion des sites
│   │   └── SyncLog.php         # Journal de synchronisation
│   └── Service/
│       └── SearchConsoleAPI.php # Client API GSC + pagination + retry
├── templates/
│   ├── auth.php               # Page de connexion
│   ├── dashboard.php          # Dashboard principal
│   ├── layout.php             # Template de base
│   └── sync-status.php        # Historique des syncs
├── .env.example               # Variables d'environnement
├── .gitignore
├── composer.json
└── README.md
```

## Notes techniques

- **Limite API** : L'API Search Console retourne maximum 25 000 lignes par requete. L'application pagine automatiquement via `startRow`.
- **Retention des donnees** : Google ne conserve les donnees de performance que ~16 mois. La synchronisation reguliere permet de constituer un historique complet dans la base locale.
- **dataState** : Le parametre `SYNC_DATA_STATE=all` dans `.env` permet de recuperer les donnees les plus fraiches (meme si pas encore finalisees par Google). Utiliser `final` pour ne recuperer que les donnees validees.
- **Deduplication** : La cle unique `(site_id, data_date, search_type, country, device, query, page)` garantit qu'une re-synchronisation met a jour les lignes existantes sans creer de doublons.
- **Tranches de dates** : La synchronisation decoupe la plage en tranches de 30 jours pour eviter les reponses trop volumineuses et limiter l'impact en cas d'erreur.
