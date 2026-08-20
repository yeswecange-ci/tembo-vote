# RUNBOOK — Soirée Castel Beer Afterwork · 21 août 2026

Ce document s'adresse à l'équipe sur place (régie, modérateurs, agence).
**Aucune compétence technique n'est nécessaire** pour les sections 1 à 6.

---

## Les 3 adresses à connaître

| Quoi | Adresse | Qui |
|---|---|---|
| Mini-site invité | `https://LE-DOMAINE/tembo` (c'est là que mène le QR) | Les invités |
| Régie (back-office) | `https://LE-DOMAINE/regie` | Christian & Hadassa |
| Mur LED | `https://LE-DOMAINE/ecran/CLE-SECRETE` (fournie à part) | Machine de régie uniquement |
| Page QR d'accès | `https://LE-DOMAINE/ecran/CLE-SECRETE/qr` | Tablette à l'entrée / second écran |

Comptes régie : `christian@tembo-vote.app` et `hadassa@tembo-vote.app`
(mots de passe transmis à part — ne jamais les écrire ici).

---

## 1. Changer la phase de la soirée

Régie → onglet **Soirée** → un bouton par phase, **un seul clic, effet immédiat partout** (invités + écran).

Ordre normal de la soirée :

1. **Préparation** — l'écran affiche le QR d'accès, rien n'est ouvert.
2. **Publication + vote** — au lancement : les invités publient et votent.
3. **Vote seul** — optionnel : on arrête les nouvelles photos, les votes continuent.
4. **Votes clos** — le classement se fige (« VOTES CLOS » sur l'écran).
5. **Révélation** — la photo gagnante en grand sur l'écran (voir section 7).
6. **Terminé** — écran de remerciement.

## 2. Comment les invités entrent (un seul QR, dynamique)

**Scanner le QR affiché sur l'écran fait entrer directement**, sans rien
saisir, et mène droit à l'action du moment : publier sa photo pendant la
publication, voter pendant le vote. Il n'existe aucun autre chemin — plus
aucun code à taper, plus aucun QR sur papier.

**Le QR change tout seul toutes les 5 minutes** (l'ancien reste valable
5 minutes de plus, personne n'est rejeté au changement). C'est ce qui
empêche une photo du QR envoyée à l'extérieur de servir toute la soirée.

- Sur **l'écran LED** : QR en haut à droite, en permanence.
- Sur la **tablette d'entrée** : page QR plein écran (`/ecran/CLÉ/qr`).
- Dans la **régie** : Tableau de bord ou onglet Soirée — le même QR, scannable
  directement sur le poste si le mur LED tombe.
- Un invité tombe sur « ce QR n'est plus valide » : il a scanné une capture
  d'écran ou un lien transmis. Faites-le rescanner l'écran, c'est tout.
- **Si un QR fuite** (partage massif hors de la salle) : `php artisan
  tembo:rotate-token` coupe court immédiatement.

## 3. Retirer une photo en urgence

Un invité demande le retrait de sa photo → moins de 30 secondes :

1. Régie → onglet **Publiées**.
2. Repérez la photo → **Retirer** → **Confirmer le retrait**.
3. Elle disparaît de la galerie et du classement en quelques secondes.

L'invité peut aussi le faire lui-même : sur son téléphone, « Ma photo »
→ « Retirer ma photo de la soirée ».

## 4. Si l'écran LED fige

1. Un **petit point doré en haut à gauche** = perte de connexion passagère :
   l'écran garde le dernier classement et se remet seul. **Ne touchez à rien.**
2. Si l'écran est vraiment figé (compteurs immobiles > 2 minutes alors que
   la salle vote) : **rechargez la page** (F5) sur la machine de régie.
3. Toujours rien : fermez le navigateur, rouvrez l'adresse de l'écran.
4. La page se recharge d'elle-même toutes les 30 minutes : un bref
   rafraîchissement est **normal**.

## 5. Si les uploads échouent

1. Un invité isolé : c'est presque toujours **sa 4G**. Le message sur son
   téléphone le lui dit — il peut réessayer, sa photo n'est pas perdue.
2. Plusieurs invités en même temps :
   - Vérifiez que la phase est bien **« Publication + vote »** (onglet Soirée).
   - Vérifiez que la régie répond (ouvrez le Tableau de bord).
   - Prévenez le contact technique (section 6) : espace disque ou serveur.
3. En dernier recours : collectez les selfies sur un téléphone du staff
   (WhatsApp) et publiez-les depuis ce téléphone via le parcours normal.

## 6. Contacts et escalade

| Rôle | Nom | Téléphone |
|---|---|---|
| Référent technique (astreinte soirée) | [À COMPLÉTER] | [À COMPLÉTER] |
| Hébergeur — support | [À COMPLÉTER] | [À COMPLÉTER] |
| Responsable agence sur place | [À COMPLÉTER] | [À COMPLÉTER] |

Escalade : modérateur → référent technique (15 min max) → plan B (section 8).

## 7. La révélation, pas à pas

Régie → onglet **Révélation**, tout est guidé :

1. **Passer en « Votes clos »** (bouton proposé si ce n'est pas fait).
2. **Relire le Top 5** : les votes suspects (même appareil ayant voté
   plusieurs fois) sont signalés en rouge. Rien n'est bloqué automatiquement :
   c'est **votre jugement** qui protège la remise du prix. Pour disqualifier
   une photo : onglet Publiées → Retirer, le classement se recalcule.
3. **Valider le classement final** (un clic). Le gagnant est enregistré.
4. **Lancer la révélation sur l'écran** : fondu de 1,2 s, photo + prénom.
5. Après le moment de scène : **Terminer — écran de remerciement**.

## 8. PLAN B — si le dispositif tombe pendant la soirée

**Le dispositif en panne ne doit jamais se voir ni s'entendre. On bascule, on ne répare pas en direct.**

1. **Diaporama de secours** : régie → Tableau de bord →
   « Exporter les photos validées (ZIP + CSV) ». Dézippez sur le PC de régie,
   lancez un diaporama plein écran (Explorateur Windows → sélectionner les
   photos → Diaporama) sur le mur LED, fond noir.
   - Si la régie ne répond plus : les photos sont aussi sur le serveur dans
     `storage/app/private/tembo/photos` (référent technique).
   - **Réflexe préventif : faites un export dès la fin des publications**,
     vous aurez toujours un diaporama prêt.
2. **Vote à main levée** : l'animateur affiche les 5 photos une à une
   (diaporama), applaudimètre ou mains levées, l'agence tranche.
3. Le CSV de l'export contient prénoms et nombre de votes au moment de
   l'export : il fait foi pour le classement si la panne survient après
   la clôture des votes.

## 9. Avant la soirée — répétition du 13 août

- [ ] `https://LE-DOMAINE/tembo` s'ouvre en 4G (pas seulement en Wi-Fi).
- [ ] Le QR de l'écran et celui de la tablette d'entrée font entrer en un scan.
- [ ] Connexion régie OK pour Christian **et** Hadassa, sur leur téléphone.
- [ ] L'écran LED affiche l'attente + le QR à la résolution réelle du mur,
      scannable depuis le fond de la salle.
- [ ] Parcours complet : scan → selfie → modération → galerie → vote → écran.
- [ ] Une révélation de test, puis remise en phase « Préparation ».
- [ ] Les sauvegardes tournent (régie → demander au référent technique).
- [ ] Les numéros de la section 6 sont remplis et enregistrés dans les téléphones.
