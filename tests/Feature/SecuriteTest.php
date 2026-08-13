<?php

it('redirige la racine vers l’écran d’accès', function () {
    $this->get('/')->assertRedirect('/tembo');
});

it('interdit l’indexation sur toutes les réponses', function () {
    // Même une réponse d'accès refusé porte l'en-tête
    $this->get('/tembo')
        ->assertForbidden()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('ne sert jamais la page de démonstration hors environnement local', function () {
    // L'environnement de test n'est pas local : la route ne doit pas exister
    $this->get('/demo-design')->assertNotFound();
});

it('affiche la mention de consommation responsable sur l’écran d’accès', function () {
    $this->get('/tembo')->assertSee(config('tembo.legal.responsible_drinking'));
});
