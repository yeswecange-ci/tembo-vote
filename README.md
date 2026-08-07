# Tembo Selfie & Vote

Dispositif web de la soirée Club Tembo (Bracongo) du **14 août 2026** :
les invités publient un selfie, votent pour la meilleure photo, un mur LED
projette le classement en direct.

- Exploitation le soir J : voir **[RUNBOOK.md](RUNBOOK.md)** (non-développeur).
- Cahier des charges : `prompt-claude-code-tembo-selfie-vote.md`.

## Prérequis serveur

| Composant | Version | Notes |
|---|---|---|
| PHP | 8.3 ou 8.4 | extensions : `gd`, `zip`, `pdo_mysql`, `mbstring`, `fileinfo`, `exif`, `curl`, `openssl` |
| MySQL / MariaDB | 8.0+ / 10.6+ | base dédiée |
| Serveur web | Apache ou nginx | racine sur `public/`, **HTTPS obligatoire** |
| Composer | 2.x | |
| Node.js | 20+ | uniquement pour compiler les assets |
| `mysqldump` | — | pour les sauvegardes (`tembo:backup`) |

Redis a été **volontairement écarté** (décision projet) : cache, sessions et
rate limiting tournent sur le driver `database`. Aucun queue worker, aucun
WebSocket : il n'y a **rien d'autre à superviser que PHP et MySQL**.

Dimensionnement : prévoir **au moins 15–20 workers PHP-FPM** (300 invités
sondant la galerie toutes les 3 s ≈ 100 req/s en pointe, servies depuis le
cache avec ETag/304). OPcache activé, `Xdebug` absent en production.

## Installation

```bash
git clone <dépôt> tembo-vote && cd tembo-vote
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# … renseigner le .env (voir ci-dessous) …
php artisan migrate --force
php artisan db:seed --force        # 2 modérateurs, 1 PIN, phase « setup »
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Permissions : `storage/` et `bootstrap/cache/` accessibles en écriture par
l'utilisateur PHP. Les photos vivent dans `storage/app/private/tembo`
(**disque privé — ne jamais exécuter `storage:link`**, la diffusion passe
par des routes signées).

## Variables d'environnement essentielles

```dotenv
APP_ENV=production
APP_DEBUG=false                        # OBLIGATOIRE
APP_URL=https://le-domaine             # sert au QR et aux URL signées
APP_TIMEZONE=Africa/Kinshasa

DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=…   # base dédiée, mot de passe fort

SESSION_SECURE_COOKIE=true             # cookies uniquement en HTTPS

TEMBO_SCREEN_KEY=…                     # openssl rand -hex 20 — clé du mur LED
TEMBO_SESSION_EXPIRES_AT="2026-08-15 06:00"
TEMBO_MODERATOR_PASSWORD=…             # FORT et unique, avant db:seed
TEMBO_MYSQLDUMP_PATH=…                 # si mysqldump n'est pas dans le PATH
```

## Tâche planifiée (sauvegardes)

Une seule entrée cron, pour la sauvegarde de la base toutes les 10 minutes
(rotation automatique, `storage/app/private/backups`) :

```
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

Aucune tâche critique du parcours invité ne dépend du cron (contrainte du
brief) : si le scheduler tombe, la soirée continue.

## Commandes d'exploitation

| Commande | Rôle |
|---|---|
| `php artisan tembo:qr` | Génère le QR d'accès (PNG + SVG) — vérifier `APP_URL` avant |
| `php artisan tembo:rotate-pin` | Force un nouveau code PIN immédiatement |
| `php artisan tembo:backup` | Sauvegarde la base (appelée par le scheduler) |
| `php artisan tembo:purge --force` | Purge post-événement (conserve les photos avec consentement de réutilisation) |
| `php artisan tembo:test-charge --url=…` | Test de charge : 300 sessions, pic d'uploads, votes, polling |

## Avant le 14 août

1. `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS valide (le
   middleware force la redirection + HSTS en production).
2. Remplacer les textes juridiques **placeholders** dans `config/tembo.php`
   (consentements, notice) — 2 minutes, aucune autre modification.
3. `php artisan tembo:qr` avec l'`APP_URL` de production → fichiers dans
   `storage/app/private/qr/` à transmettre aux graphistes.
4. `php artisan tembo:test-charge --url=https://le-domaine` depuis une
   machine séparée, et comparer aux chiffres de référence.
5. Répétition du 13 août : dérouler la checklist du RUNBOOK section 9.

## Développement

```bash
composer install && npm install
php artisan migrate --seed
composer run dev          # serveur + vite
php artisan test --compact
vendor/bin/pint
```
