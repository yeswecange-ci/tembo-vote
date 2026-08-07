# Prompt Claude Code — Dispositif « Selfie & Vote » Club Tembo (Bracongo)

> À coller dans Claude Code au démarrage du projet.
> **Règle absolue : les modules sont séquentiels. Tu termines un module, tu me le présentes, tu attends ma validation explicite avant de passer au suivant.** Ne construis jamais deux modules d'affilée sans validation.

---

## 0. Contexte

Tu construis une application web Laravel pour une **activation événementielle unique** : la soirée Club Tembo du **vendredi 14 août 2026**, organisée par Bracongo (RDC) pour sa marque de bière **Tembo**.

100 à 300 invités premium — dirigeants d'entreprise, capitaines d'industrie, autorités publiques — prennent un selfie avec une Tembo 50 Cl depuis leur propre téléphone, le publient sur un mini-site privé après modération, et votent pour la meilleure photo. Un classement en temps réel est projeté sur un **mur LED de 3 × 4 m** dans la salle. Le gagnant est révélé sur scène en fin de soirée.

### Contraintes non négociables

1. **Événement unique, date fixe, aucune seconde chance.** L'application tourne 5 heures et doit être irréprochable pendant ces 5 heures. **La robustesse prime sur toute élégance architecturale.**
2. **8 jours de développement.** Zéro over-engineering. Aucune abstraction « au cas où on réutiliserait ». Chaque ligne sert la soirée du 14 août.
3. **100 % web.** Aucune installation d'application. Navigateurs cibles : Safari iOS 16+, Chrome Android récent. Le desktop ne sert qu'au back-office et au mur LED.
4. **Dispositif privé.** Aucun accès sans session valide, aucune indexation, expiration automatique.
5. **Réseau non fiable.** Chaque invité est sur sa propre 4G, de qualité variable. Toute fonctionnalité se dégrade proprement, jamais ne plante.
6. **Zéro friction.** Maximum 3 écrans entre le scan du QR et la photo envoyée. Cette cible abandonne au premier temps d'attente inexpliqué.

### Ce qui est déjà tranché par le client

| Point | Décision |
|---|---|
| Accès | **Aucune impression.** Un QR unique affiché en salle (totems, table numbers), pas de QR nominatif. |
| Nombre d'invités | Inconnu à l'avance, estimation 100–300. Aucun pré-enregistrement possible. |
| Modération | 2 modérateurs (Christian, Hadassa) sur leur propre matériel et leur propre connexion. **Prévoir une interface qui fonctionne aussi bien sur téléphone que sur laptop.** |
| Photos par invité | **Une seule.** |
| Écran | Mur LED 3 × 4 m. **Résolution et ratio non confirmés à ce jour** → le Mode Écran doit être entièrement fluide. |
| Répétition sur site | Accordée le 13 août. |
| Textes juridiques | Fournis par le client, non encore reçus → **placeholders isolés dans un unique fichier de config, remplaçables en 2 minutes sans toucher au code.** |

### Style de code attendu

- **Français** pour les commentaires, les messages utilisateur, les libellés d'interface, les messages d'erreur.
- Anglais pour les noms de classes, méthodes, variables, tables et colonnes.
- Commentaires uniquement sur les choix non évidents (le *pourquoi*), jamais pour paraphraser le code.
- Gestion d'erreurs systématique. Aucun `try/catch` vide, aucune erreur avalée en silence.
- Pas de package tiers sans justification. Les seuls autorisés d'office : `intervention/image` (v3), `endroid/qr-code`.

---

## 1. Stack technique imposée

| Couche | Choix | Pourquoi |
|---|---|---|
| Framework | Laravel 12, PHP 8.3+ | — |
| Base | MySQL 8 / MariaDB 10.6+ | — |
| Cache / rate limit | **Redis** | Compteurs de vote atomiques + throttling. Si Redis indisponible sur l'hébergement, bascule sur le driver `database`, mais signale-le-moi. |
| Front | **Blade + Alpine.js 3 + Vite + Tailwind CSS 4** | Alpine garde l'état côté client et survit à une requête perdue. Livewire renvoie du HTML à chaque interaction et se comporte mal sur connexion instable. |
| Temps réel | **Polling JSON, pas de WebSocket** | Un process Reverb de plus à surveiller = un point de panne de plus pendant une soirée sans droit à l'erreur. Le polling se remet tout seul d'une coupure. |
| Images | `intervention/image` v3 (driver GD) | — |
| Traitement image | **Synchrone, dans la requête** | Pas de queue, pas de Supervisor à babysitter le soir de l'événement. Les images arrivent déjà compressées côté client (~250 Ko), le re-encodage prend ~200 ms. |

**Interdits explicites :** Livewire, Reverb / WebSockets, Inertia, React/Vue, queue worker, cron pour des tâches critiques de la soirée.

---

## 2. Direction artistique

La cible est premium et exigeante. Le design doit être **sobre, dense, silencieux** — l'élégance vient de la précision des espacements et de la typographie, pas d'effets. Aucune décoration qui ne sert pas la lecture.

Référence mentale : l'identité visuelle d'une maison de spiritueux, pas celle d'une activation grand public. Le rouge Tembo est puissant : il s'utilise **en accent, jamais en aplat de fond**.

### Tokens de couleur — à déclarer dans `@theme` Tailwind et nulle part ailleurs

```css
@theme {
  /* Fonds — noir chaud, jamais gris bleuté */
  --color-nuit:      #12100F;  /* fond global */
  --color-nuit-haut: #1C1917;  /* cartes, surfaces élevées */
  --color-nuit-bord: #2A2624;  /* bordures, séparateurs */

  /* Texte */
  --color-ivoire:    #EFEAE1;  /* texte principal */
  --color-ivoire-bas:#8F8880;  /* texte secondaire, libellés */

  /* Marque — extraits du logo officiel */
  --color-rouge:     #ED1C24;  /* accent d'action, exclusivement */
  --color-or:        #8C734B;  /* filets, cadres, ornements */
  --color-or-clair:  #C4A56E;  /* or lisible en TEXTE sur fond noir */
}
```

**Attention contraste :** `#8C734B` sur `#12100F` donne un ratio d'environ 3.5:1, insuffisant pour du texte. L'or foncé sert aux **filets et bordures uniquement** ; dès qu'il s'agit de texte, utilise `--color-or-clair`.

**Interdiction stricte :** aucune couleur par défaut de Tailwind (`gray-800`, `red-500`, `slate-*`…). Si une couleur n'est pas dans les tokens ci-dessus, elle n'existe pas.

### Typographie

| Rôle | Police | Usage |
|---|---|---|
| Display | **Archivo** (variable, `wdth` 110–125, `wght` 700–800) | Titres, classement, révélation. Fait écho au lettrage large et massif du bandeau TEMBO. Toujours en majuscules avec `letter-spacing: 0.02em`. |
| Texte | **Instrument Sans** (400 / 500) | Corps, libellés, boutons, textes légaux. |
| Chiffres | **JetBrains Mono** (500) | Compteurs de votes, code PIN, rangs. Choix fonctionnel : une police à chasse fixe empêche la largeur de sauter quand un compteur passe de 9 à 10 en animation. |

Échelle typographique fixe, pas d'improvisation : `12 / 14 / 16 / 20 / 26 / 34 / 48 / 72`.

### Élément signature — le ruban Tembo

Le logo contient un **bandeau rouge aux bords ondulés**. C'est la forme la plus reconnaissable de la marque. Tu la reprends comme motif structurel, sous forme d'un SVG de séparation à bord ondulé, et **exactement à deux endroits** :

1. Sous le titre du Mode Écran (en or, filet fin).
2. Comme bord supérieur de la barre de vote fixe en bas de l'écran mobile (en rouge, pleine).

Nulle part ailleurs. C'est le seul écart décoratif autorisé du projet.

### Règles de mise en page

- Grille mobile : marge latérale 20 px, gouttière 12 px.
- Rayons : `4px` partout, `0` sur les éléments pleine largeur. Jamais de `rounded-full` sauf sur l'avatar de rang.
- Ombres : aucune. La hiérarchie se fait par la valeur du fond (`nuit` → `nuit-haut`) et par des filets `1px` en `--color-nuit-bord`.
- Le logo Tembo fourni est en rouge sur fond blanc. Sur fond noir, l'utiliser tel quel dans une **pastille ivoire** de 56 px, ou demander une version blanche. Ne jamais l'afficher directement sur le noir sans fond.

### Mouvement

Trois animations autorisées dans tout le projet, pas une de plus :

1. Apparition des nouvelles photos dans la galerie : `opacity 0→1` + `translateY 8px→0`, 240 ms.
2. Compteur de votes : incrément numérique animé sur 400 ms.
3. Révélation du gagnant : fondu de 1200 ms sur l'écran LED.

`@media (prefers-reduced-motion: reduce)` désactive tout. Aucun effet au survol autre qu'un changement d'opacité.

### Plancher de qualité, non négociable

- Responsive de 320 px à 1920 px, testé sur les deux.
- Zones tactiles minimum 44 × 44 px.
- Focus clavier visible sur tous les éléments interactifs.
- Chaque état de chargement, d'erreur et de vide est **conçu**, pas laissé par défaut. Un écran vide est une invitation à agir, pas un trou.
- Les messages d'erreur disent ce qui s'est passé et quoi faire. Pas de « Une erreur est survenue ».

---

## 3. Modèle de données

```
guest_sessions
  id (ulid) · device_hash · ip_hash · pin_used
  created_at · expires_at · revoked_at

access_pins
  id · code (4 chiffres) · valid_from · valid_until · created_at

photos
  id (ulid) · guest_session_id · display_name
  path · thumb_path
  status  enum[pending, approved, rejected]
  reject_reason (nullable) · moderated_by · moderated_at
  consent_event (bool, obligatoire) · consent_reuse (bool, optionnel)
  votes_count (int, dénormalisé, default 0)
  created_at
  INDEX (status, created_at)

votes
  id · guest_session_id · photo_id · device_hash · created_at
  UNIQUE (guest_session_id)          ← garantit 1 vote actif par session
  INDEX (device_hash)                ← détection des doublons, NON bloquant

moderators
  table users standard Laravel, 2 comptes seedés

audit_logs
  id · action · actor · target_type · target_id · ip · meta (json) · created_at

settings
  key · value        ← phase courante, textes juridiques, drapeaux
```

**Points d'attention :**

- `votes_count` est dénormalisé. Un `COUNT` sur toutes les photos toutes les 2 secondes pendant 5 heures, ce n'est pas viable. Il est mis à jour dans la **même transaction** que le vote.
- L'index sur `votes.device_hash` **ne bloque pas**. Il sert uniquement à signaler les doublons potentiels dans le back-office. Refuser le vote d'un directeur général parce que l'algorithme l'a confondu avec son voisin (même modèle de téléphone, même opérateur) serait bien pire qu'un vote en trop.
- `display_name` est du contenu utilisateur : il passe par la modération au même titre que la photo.

### Machine à états de la soirée

```
setup → open → vote_only → frozen → reveal → closed
```

| Phase | Publication | Vote | Écran |
|---|---|---|---|
| `setup` | ✗ | ✗ | Écran d'attente + logo + PIN |
| `open` | ✓ | ✓ | Classement live |
| `vote_only` | ✗ | ✓ | Classement live |
| `frozen` | ✗ | ✗ | Classement figé (« Votes clos ») |
| `reveal` | ✗ | ✗ | Révélation du gagnant |
| `closed` | ✗ | ✗ | Remerciement |

La phase est stockée dans `settings`, mise en cache, et changée depuis le back-office **en un clic, sans confirmation à plusieurs étapes** — le régisseur est pressé et dans le noir.

---

## 4. Modules de développement

### Module 0 — Socle et design system

- Projet Laravel neuf, `.env.example` documenté, migrations complètes, seeders (2 modérateurs, 1 PIN initial, phase `setup`).
- Configuration Tailwind 4 avec les tokens ci-dessus, polices auto-hébergées (**pas de Google Fonts en CDN** : dépendance réseau externe le soir J, inacceptable).
- Layout Blade de base : `layouts/guest.blade.php` (mobile), `layouts/screen.blade.php` (LED), `layouts/admin.blade.php`.
- Composants Blade : `x-bouton`, `x-champ`, `x-alerte`, `x-ruban` (le SVG signature), `x-etat-vide`.
- Middleware global : en-têtes `X-Robots-Tag: noindex, nofollow, noarchive`, `robots.txt` bloquant tout, HTTPS forcé.
- Un fichier `config/tembo.php` centralisant : durée de rotation du PIN, phase, textes juridiques, seuils de rate limiting, taille max d'upload.

**Livrable à me présenter :** une page de démonstration statique montrant tous les composants et l'échelle typographique, pour valider la direction artistique avant d'aller plus loin.

---

### Module 1 — Accès (QR + PIN rotatif)

Le client a refusé le QR nominatif. Le contrôle d'accès repose donc sur un **code PIN rotatif affiché sur le mur LED**.

- Le QR mène à `/tembo` → écran de saisie du code à 4 chiffres, clavier numérique natif (`inputmode="numeric"`, `autocomplete="one-time-code"`).
- **Rotation toutes les 20 minutes, avec 2 codes valides en glissement.** Sans le chevauchement, l'invité qui scanne pile au moment du changement se fait rejeter et abandonne.
- Commande Artisan `tembo:rotate-pin` + génération à la volée si le PIN courant est expiré (ne dépends pas d'un cron pour ça, c'est trop critique).
- PIN correct → création d'une `guest_session`, cookie signé `httpOnly` / `secure` / `SameSite=Lax`, expiration à minuit.
- **Rate limiting : 5 tentatives / 10 min / IP.** Quatre chiffres se brute-forcent en quelques secondes sans ça. Message d'erreur explicite après blocage, avec le délai restant.
- Middleware `guest.session` protégeant toutes les routes invité.
- Page d'accueil sobre après connexion : logo, message de bienvenue court, deux actions — **Publier ma photo** / **Voter**.

**Sécurité :** `device_hash` = SHA-256 de `IP + User-Agent + APP_KEY`, stocké haché. Jamais d'IP en clair en base.

---

### Module 2 — Publication de la photo

Parcours en 3 écrans maximum : capture → nom + consentement → confirmation.

**Compression côté client, obligatoire.** Un selfie iPhone brut pèse 4 Mo ; sur une 4G moyenne c'est 30 secondes d'attente et un invité qui abandonne.

```js
// Redimensionnement à 1600 px max puis ré-encodage JPEG qualité 0.8 → ~250 Ko.
// Le passage par le canvas supprime au passage les métadonnées EXIF,
// dont la géolocalisation — obligatoire pour cette cible.
```

- Champ de capture : `<input type="file" accept="image/*" capture="user">`, avec import galerie autorisé en repli.
- Aperçu avant envoi, possibilité de reprendre.
- Champ prénom / pseudo : 2 à 24 caractères, `strip_tags`, une seule ligne.
- **Écran de consentement** : case obligatoire (affichage pendant la soirée) + case séparée et facultative (réutilisation après l'événement). Textes tirés de `config/tembo.php`. La case facultative est décochée par défaut — c'est une exigence légale, pas un choix de design.
- Mention de consommation responsable en pied de page, discrète mais présente sur tous les écrans.
- Barre de progression réelle pendant l'envoi (`XMLHttpRequest.upload.onprogress`). Un spinner indéterminé sur 4G lente donne l'impression que c'est planté.
- Une seule photo par session : après envoi, le bouton « Publier » devient « Ma photo » et affiche son statut (en attente / publiée / refusée avec motif).
- Écran de confirmation : « Ta photo est en cours de validation. Elle apparaîtra dans la galerie d'ici quelques instants. »

**Pipeline serveur, dans cet ordre exact :**

```
1. validate: image | mimes:jpeg,png,heic | max:5120
2. Intervention Image → ré-encodage JPEG forcé (détruit tout fichier polyglotte)
3. Génération d'une vignette 400px pour la galerie
4. Nom de fichier = ULID. Le nom d'origine n'est jamais réutilisé.
5. Stockage sur disque PRIVÉ (storage/app/tembo), jamais dans public/
6. Diffusion via route signée uniquement
```

**Ne fais jamais confiance au `mimeType` envoyé par le navigateur.** Le stockage en disque public avec lien symbolique exposerait les photos **rejetées** par URL devinable — inacceptable avec cette cible.

Rate limit : 3 tentatives d'upload / minute / session.

---

### Module 3 — Back-office de modération

Deux modérateurs, sur leur propre matériel, possiblement sur téléphone. **Conçois cette interface mobile-first**, c'est le poste le plus sollicité de la soirée.

- Auth Laravel standard, throttle sur le login, 2 comptes seedés. Pas d'inscription publique.
- **File d'attente** : photos `pending`, la plus ancienne en premier, rafraîchissement automatique toutes les 5 s.
- Deux boutons pleine largeur, très espacés pour éviter l'erreur au pouce : **Valider** / **Refuser**. Le refus demande un motif choisi dans une liste courte (produit absent, contenu inapproprié, photo illisible, autre).
- Affichage du compteur de photos en attente en gros, en permanence.
- Vue « Publiées » avec possibilité de **retirer une photo déjà en ligne** à tout moment. C'est une obligation du brief, pas une option : un invité peut demander le retrait pendant la soirée.
- Verrou anti-collision : si deux modérateurs ouvrent la même photo, le second reçoit un message clair. Un simple `updated_at` en condition de mise à jour suffit, ne construis pas un système de verrous.
- Contrôle de la phase de soirée : 6 boutons, un clic, effet immédiat.
- Toute action est écrite dans `audit_logs`.

---

### Module 4 — Galerie et vote

- Galerie en grille 2 colonnes, pagination par curseur (`created_at` + `id`), chargement à la volée au défilement.
- Polling `GET /api/gallery?after=<cursor>` toutes les **3 secondes**, réponse servie depuis un cache Redis invalidé à chaque validation de photo. `ETag` + réponse `304` pour ne pas retransmettre le même corps 6000 fois par heure.
- Vote : un appui sur une photo. **Un seul vote actif par session**, changeable — `updateOrCreate` sur `guest_session_id`, jamais d'insertion.
- La photo votée est marquée d'un filet rouge de 2 px et d'un libellé « Mon vote ». Aucune autre indication.
- Barre fixe en bas d'écran (avec le ruban rouge en bord supérieur) : rappel du vote en cours + accès au classement.
- Incrémentation / décrémentation du `votes_count` **dans la même transaction** que l'écriture du vote.
- Le total de votes de chaque photo n'est **pas affiché dans la galerie** : ça crée un effet de meute où tout le monde vote pour le meneur. Les chiffres ne vivent que sur le mur LED.
- Rate limit : 10 votes / minute / session.
- Si le polling échoue, l'interface garde le dernier état connu et affiche un indicateur discret de reconnexion. Elle ne se vide jamais.

---

### Module 5 — Mode Écran (mur LED)

Route dédiée `/ecran/{cle}`, protégée par une clé secrète en config, sans aucune interaction.

**La résolution du mur LED n'est pas connue.** Conséquence directe : **toute la mise en page est en `vw` / `vh` / `clamp()`, aucune valeur en pixels fixes.** Tu la testes en 1024×768, 1280×720, 1920×1080 et en ratio portrait 3:4. Elle doit rester lisible dans les quatre cas.

Contenu :

- Titre en Archivo majuscules + ruban or.
- **Le PIN courant affiché en permanence** dans un coin, en JetBrains Mono, taille suffisante pour être lu depuis le fond de la salle.
- Top 5 : rang en or monospace surdimensionné, vignette, prénom, compteur de votes animé.
- Bandeau du bas : nombre de photos publiées, nombre total de votes, mention de consommation responsable.
- Polling toutes les **2 secondes** sur `/api/leaderboard`.
- **Rechargement automatique de la page toutes les 30 minutes**, à la seconde 0 d'une minute paire. Une page ouverte 5 heures d'affilée accumule les fuites mémoire et finit par ramer sur une machine de régie modeste.
- **Si le polling échoue, l'écran conserve le dernier classement connu.** Il n'affiche jamais d'erreur, jamais une page blanche, jamais un état vide devant 300 personnes. Un point discret indique la perte de connexion.
- Fond `--color-nuit` pur : un mur LED en pleine luminosité sur du blanc est éblouissant et rend les photos illisibles.

---

### Module 6 — Révélation du gagnant

- Phase `frozen` : le classement se fige, l'écran affiche « Votes clos ».
- **Le back-office affiche le Top 5 avec les votes suspects signalés** (doublons de `device_hash`), et un bouton « Valider le classement final ».
- Un humain valide avant que le prix ne parte sur scène. **C'est la vraie protection anti-triche du dispositif**, pas le code : sans QR nominatif, aucun contrôle automatique n'est fiable à 100 %, mais une relecture humaine du Top 5 l'est.
- Phase `reveal` : fondu de 1200 ms, photo gagnante en plein écran, prénom en Archivo, ruban or. Aucune animation supplémentaire — c'est un moment de scène, l'écran doit servir la personne au micro, pas la concurrencer.
- Phase `closed` : écran de remerciement.

---

### Module 7 — Export, durcissement, exploitation

- Export ZIP des photos validées + CSV (prénom, votes, rang, consentement réutilisation, horodatage). Accessible depuis le back-office.
- Commande `tembo:purge` supprimant photos et sessions au-delà de la durée retenue, en conservant celles avec `consent_reuse = true`.
- Route de retrait sur demande, utilisable pendant et après la soirée.
- **Sauvegarde automatique** de la base toutes les 10 minutes pendant la soirée (commande Artisan + tâche planifiée), avec rotation.
- **Checklist de sécurité vérifiée point par point**, avec le résultat de chaque point :
  - CSRF actif sur tous les POST
  - `X-Robots-Tag` et `robots.txt` en place
  - Photos servies exclusivement par route signée
  - Rate limiting actif sur : PIN, upload, vote, login admin
  - Cookies `httpOnly` + `secure` + `SameSite`
  - HTTPS forcé, HSTS
  - `APP_DEBUG=false` en production, page d'erreur personnalisée
  - Aucune donnée personnelle en clair en base
  - Login admin non devinable, mots de passe forts
- **Test de charge** : script simulant 300 sessions — pic d'uploads simultanés, 300 votes en 2 minutes, polling continu. Rapporte les temps de réponse mesurés, ne te contente pas de dire que ça passe.
- **`RUNBOOK.md`** en français, destiné à quelqu'un qui n'est pas développeur :
  - Comment changer la phase de soirée
  - Comment lire le PIN courant
  - Comment retirer une photo en urgence
  - Que faire si l'écran fige
  - Que faire si les uploads échouent
  - Contacts et procédure d'escalade
  - **Plan B écrit** : si le dispositif tombe, diaporama des photos déjà validées + vote à main levée

---

## 5. Attendus transversaux

- **Après chaque module**, tu me présentes : ce qui est fait, comment le tester, les décisions que tu as prises et qui n'étaient pas dans le prompt, ce qui reste ouvert.
- Tu ne modifies jamais un module validé sans me le dire explicitement.
- Si une contrainte du prompt te paraît mauvaise, **dis-le et propose autre chose** avant de l'implémenter. Je préfère un désaccord argumenté à une exécution silencieuse.
- Aucune donnée d'exemple codée en dur dans les vues. Tout vient de la base ou de la config.
- Un `README.md` de déploiement : prérequis serveur, variables d'environnement, commandes d'installation, configuration Redis, permissions des dossiers de stockage.

## 6. Premier pas

Ne commence pas à coder immédiatement. Commence par :

1. Reformuler ce que tu as compris du dispositif en 10 lignes.
2. Lister les points du prompt qui te semblent ambigus ou risqués.
3. Proposer ton plan pour le Module 0.

Puis attends ma validation.
