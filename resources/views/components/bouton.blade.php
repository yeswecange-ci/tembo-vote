{{--
    Bouton unique du projet. Trois variantes, zone tactile 44 px minimum,
    survol limité à un changement d'opacité (règle du brief).
--}}
@props([
    'variante' => 'primaire', {{-- primaire (rouge) | secondaire (filet) | discret (texte seul) --}}
    'type' => 'button',
    'href' => null,
])

@php
    // active:* : le retour d'appui compte plus que le survol — la cible est sur mobile
    $classes = 'inline-flex min-h-11 select-none items-center justify-center gap-2 rounded px-6 text-16 font-medium transition-opacity disabled:pointer-events-none disabled:opacity-40 '.match ($variante) {
        'secondaire' => 'border border-nuit-bord bg-nuit-haut text-ivoire hover:opacity-85 active:opacity-70',
        'discret' => 'text-ivoire-bas hover:opacity-75 active:opacity-60',
        default => 'bg-rouge text-creme hover:opacity-90 active:opacity-75',
    };
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
