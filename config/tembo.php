<?php

/*
|--------------------------------------------------------------------------
| Dispositif Selfie & Vote — soirée Club Tembo du 14 août 2026
|--------------------------------------------------------------------------
| Tout ce qui doit pouvoir changer sans toucher au code est centralisé ici :
| textes juridiques, seuils, durées, clés. Rien de tout cela n'est codé en
| dur ailleurs dans l'application.
*/

return [

    // Phase de la soirée au premier démarrage, avant toute écriture en base
    'default_phase' => 'setup',

    /*
    |--------------------------------------------------------------------------
    | Accès invité (QR rotatif, affiché sur écran — jamais sur papier)
    |--------------------------------------------------------------------------
    | Le QR est le seul chemin d'entrée : le scanner ouvre la session, il n'y
    | a rien à saisir. Sa rotation est ce qui limite la portée d'un QR
    | photographié puis partagé hors de la salle.
    */
    'access' => [
        // Un QR capturé en photo cesse de fonctionner au bout de
        // rotation_minutes × valid_tokens, soit 10 minutes au maximum
        'rotation_minutes' => 5,
        // Nombre de jetons acceptés simultanément : sans chevauchement, l'invité
        // qui scanne pile au moment de la rotation se fait rejeter et abandonne
        'valid_tokens' => 2,
        // Plus personne ne saisit ce jeton à la main : il n'a aucune raison
        // d'être court, et cette longueur le rend indevinable
        'token_length' => 32,
    ],

    // Expiration de toutes les sessions invité, heure de Kinshasa.
    // Le 15 à 06:00 et non minuit : la soirée du 14 se termine après minuit.
    'session_expires_at' => env('TEMBO_SESSION_EXPIRES_AT', '2026-08-15 06:00'),

    /*
    |--------------------------------------------------------------------------
    | Limites de débit (anti brute-force et anti-abus)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        // Aucun verrou sur l'entrée : le jeton du QR n'est pas devinable, et
        // toute la salle partage la même IP publique — un blocage par IP y
        // ferait tomber les invités légitimes en pleine arrivée.
        'upload' => ['attempts' => 3, 'decay_minutes' => 1],
        // Le multi-vote rend légitime une rafale d'appuis : la limite ne protège
        // plus du double vote (l'unicité en base s'en charge) mais du martelage.
        'vote' => ['attempts' => 60, 'decay_minutes' => 1],
        'admin_login' => ['attempts' => 5, 'decay_minutes' => 10],
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */
    // Taille maximale acceptée en Ko (la compression client vise ~250 Ko)
    'upload_max_kb' => 5120,
    'image' => [
        'max_width' => 1600,
        'thumb_width' => 400,
        'jpeg_quality' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mur LED
    |--------------------------------------------------------------------------
    */
    // Clé secrète de la route /ecran/{cle}
    'screen_key' => env('TEMBO_SCREEN_KEY'),
    // Rechargement complet de la page écran (fuites mémoire sur 5 h d'affichage)
    'screen_reload_minutes' => 30,

    // Intervalles de polling, en secondes
    'polling' => [
        'gallery' => 3,
        'leaderboard' => 2,
        'moderation' => 5,
    ],

    // Mot de passe des 2 comptes modérateurs seedés
    'moderator_password' => env('TEMBO_MODERATOR_PASSWORD'),

    // Motifs de refus proposés aux modérateurs (liste courte, brief Module 3)
    'reject_reasons' => [
        'produit absent',
        'contenu inapproprié',
        'photo illisible',
        'autre',
    ],

    // Motif enregistré quand une photo publiée est retirée en cours de soirée
    'removal_reason' => 'retirée à la demande',

    /*
    |--------------------------------------------------------------------------
    | Exploitation (Module 7)
    |--------------------------------------------------------------------------
    */
    // tembo:purge supprime photos et sessions au-delà de ce délai,
    // en conservant les photos avec consent_reuse = true
    'purge_after_days' => (int) env('TEMBO_PURGE_AFTER_DAYS', 30),

    // tembo:backup : nombre de sauvegardes conservées (rotation)
    'backup_keep' => (int) env('TEMBO_BACKUP_KEEP', 40),

    // Chemin de mysqldump si absent du PATH (WAMP, XAMPP…)
    'mysqldump_path' => env('TEMBO_MYSQLDUMP_PATH', 'mysqldump'),

    /*
    |--------------------------------------------------------------------------
    | Textes juridiques
    |--------------------------------------------------------------------------
    | Seules les chaînes ci-dessous sont à modifier, rien d'autre à toucher.
    */
    'legal' => [
        // Mention discrète mais présente en pied de page de tous les écrans
        'responsible_drinking' => "L'abus d'alcool est dangereux pour la santé. À consommer avec modération.",

        // Notice d'information (droit de retrait, contact)
        'privacy_notice' => "Votre photo n'est visible que par les invités de la soirée. Vous pouvez en demander le retrait à tout moment auprès du personnel.",
    ],
];
