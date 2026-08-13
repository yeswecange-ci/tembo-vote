<?php

test('the application returns a successful response', function () {
    // La racine redirige vers l'entrée /tembo, qui n'ouvre qu'avec le
    // jeton du QR : sans lui, la porte reste fermée (403).
    $this->get('/')->assertRedirect('/tembo');

    $this->get('/tembo')->assertForbidden();
});
