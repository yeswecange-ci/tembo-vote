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
    | Accès invité (QR + PIN rotatif)
    |--------------------------------------------------------------------------
    */
    'pin' => [
        // Durée de vie d'un code avant rotation
        'rotation_minutes' => 20,
        // Nombre de codes acceptés simultanément : sans chevauchement, l'invité
        // qui scanne pile au moment du changement se fait rejeter et abandonne
        'valid_codes' => 2,
        'length' => 4,
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
        // 4 chiffres se brute-forcent en quelques secondes sans ce verrou
        'pin' => ['attempts' => 5, 'decay_minutes' => 10],
        'upload' => ['attempts' => 3, 'decay_minutes' => 1],
        'vote' => ['attempts' => 10, 'decay_minutes' => 1],
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
    | Textes juridiques — PLACEHOLDERS à remplacer dès réception du client
    |--------------------------------------------------------------------------
    | Seules les chaînes ci-dessous sont à modifier, rien d'autre à toucher.
    */
    'legal' => [
        // Case OBLIGATOIRE : affichage pendant la soirée
        'consent_event' => "J'accepte que ma photo et mon prénom soient affichés dans la galerie et sur l'écran de la soirée Club Tembo du 14 août 2026. [PLACEHOLDER — texte définitif à fournir par Bracongo]",

        // Mention discrète mais présente en pied de page de tous les écrans
        'responsible_drinking' => "L'abus d'alcool est dangereux pour la santé. À consommer avec modération.",

        // Notice d'information (droit de retrait, contact)
        'privacy_notice' => "Votre photo n'est visible que par les invités de la soirée. Vous pouvez en demander le retrait à tout moment auprès du personnel. [PLACEHOLDER — texte définitif à fournir par Bracongo]",
    ],
];
