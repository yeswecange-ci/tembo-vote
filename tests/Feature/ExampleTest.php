<?php

test('the application returns a successful response', function () {
    // La racine redirige vers l'écran d'accès /tembo (parcours QR)
    $response = $this->get('/');

    $response->assertRedirect('/tembo');

    $this->get('/tembo')->assertStatus(200);
});
