# Tembo Selfie & Vote — Mémoire de référence

Cahier des charges complet : `prompt-claude-code-tembo-selfie-vote.md` (fait autorité).
**Modules séquentiels : validation explicite de l'utilisateur avant de passer au module suivant.**

## Les 6 contraintes non négociables

**Accès (décision client du 13/08, remplace celle du 7/08)** : **un seul QR, dynamique, affiché sur écran — jamais sur papier**. Il embarque un jeton opaque de 32 caractères, renouvelé toutes les 5 minutes (2 jetons valides en glissement) : scanner = entrer (`/tembo?t=JETON`), et un QR photographié puis partagé hors de la salle est mort en 10 minutes au plus. **Aucune saisie manuelle, aucun code affiché, aucun repli** : le PIN à 4 chiffres et la commande `tembo:qr` (QR imprimable) ont été supprimés. Le scan mène droit à l'action de la phase courante — publication en `open`, galerie en `vote_only`, accueil sinon.

**Vote (décision client du 20/08/2026, remplace la règle du vote unique du cahier des charges)** : l'invité vote pour **autant de photos qu'il veut, une seule fois par photo** (un second appui retire le vote), et **jamais pour la sienne**. L'unicité en base porte sur `(guest_session_id, photo_id)`. Le **classement invité affiche le nombre de votes** par photo, au nom de la transparence ; **la galerie reste sans chiffres** — l'effet de meute se joue au moment du choix, pas après. Conséquence en régie : un même `device_hash` portant plusieurs votes est devenu normal, le signal de fraude est désormais un `device_hash` derrière **plusieurs sessions invité**.

1. **Événement unique** — soirée du 21/08/2026, 5 heures, aucune seconde chance. La robustesse prime sur l'élégance architecturale.
2. **8 jours de dev** — zéro over-engineering, aucune abstraction « au cas où ».
3. **100 % web** — Safari iOS 16+, Chrome Android récent. Desktop uniquement pour back-office et mur LED.
4. **Privé** — aucun accès sans session valide, noindex total, expiration automatique.
5. **Réseau 4G non fiable** — toute fonctionnalité se dégrade proprement, jamais ne plante. L'interface ne se vide jamais.
6. **Zéro friction** — maximum 3 écrans entre le scan du QR et la photo envoyée.

## Tokens de couleur (déclarés dans `@theme` Tailwind, nulle part ailleurs)

**THÈME CLAIR** (décision client du 7/08) — les noms restent sémantiques, seules les valeurs ont changé. **Le mur LED garde le thème sombre d'origine** via la classe `.theme-sombre` (layout screen) qui redéfinit les variables.

| Token | Clair (défaut) | Sombre (`.theme-sombre`, écran LED) | Usage |
|---|---|---|---|
| `nuit` | `#FAF7F2` | `#12100F` | fond global |
| `nuit-haut` | `#FFFFFF` | `#1C1917` | cartes, surfaces élevées |
| `nuit-bord` | `#E7E1D6` | `#2A2624` | bordures, séparateurs 1px |
| `ivoire` | `#1F1B18` | `#EFEAE1` | texte principal |
| `ivoire-bas` | `#6E675E` | `#8F8880` | texte secondaire |
| `rouge` | `#C8161D` | `#ED1C24` | accent d'action **exclusivement** |
| `or` | `#8C734B` | `#8C734B` | filets/cadres **uniquement**, jamais en texte |
| `or-clair` | `#6E5836` | `#C4A56E` | l'or quand c'est du **texte** |
| `creme` | `#F5F1E8` | `#F5F1E8` | invariant : pastille logo, texte sur fond rouge |

**Interdit : toute couleur Tailwind par défaut** (`gray-*`, `red-*`, `slate-*`…). Hors tokens, la couleur n'existe pas.

## Typographie (3 polices, auto-hébergées — jamais de CDN)

- **Archivo** (variable, `wdth` 110–125, `wght` 700–800) — display : titres, classement, révélation. Toujours MAJUSCULES + `letter-spacing: 0.02em`.
- **Instrument Sans** (400/500) — corps, libellés, boutons, textes légaux.
- **JetBrains Mono** (500) — chiffres : compteurs de votes, PIN, rangs.

Échelle fixe : `12 / 14 / 16 / 20 / 26 / 34 / 48 / 72`. Espacements = multiples de 4. Rayons `4px` (`0` en pleine largeur, `rounded-full` seulement sur l'avatar de rang). Aucune ombre — hiérarchie par valeur de fond + filets 1px.

## Stack imposée

Laravel 12+ / PHP 8.3+ · MySQL 8 · Redis pour cache/compteurs/throttling (fallback driver `database` si indisponible, **à signaler**) · Blade + Alpine.js 3 + Vite + Tailwind CSS 4 · **Polling JSON** pour le temps réel · `intervention/image` v3 (GD), traitement **synchrone dans la requête** · `endroid/qr-code`.

## Interdits techniques

Livewire · Reverb/WebSockets · Inertia · React/Vue · queue worker · cron pour les tâches critiques de la soirée · Google Fonts en CDN · tout package tiers non autorisé · photos dans `public/` (disque privé + route signée uniquement) · IP en clair en base.

## Style de code et qualité

- **Français** : commentaires, messages utilisateur, libellés, messages d'erreur. **Anglais** : classes, méthodes, variables, tables, colonnes.
- Commentaires uniquement sur le *pourquoi*, jamais de paraphrase. Aucun `try/catch` vide, aucune erreur avalée.
- Chaque écran est conçu dans ses **4 états** : normal, chargement, erreur, vide. Testé à **320px et 430px** avant de déclarer un module fini.
- Les erreurs disent ce qui s'est passé et quoi faire — jamais « Une erreur est survenue ».
- Ruban SVG ondulé Tembo à **exactement 2 endroits** (titre Mode Écran en or, bord supérieur barre de vote mobile en rouge).
- **3 animations dans tout le projet** : apparition galerie 240ms, compteur 400ms, révélation 1200ms. `prefers-reduced-motion` désactive tout.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
