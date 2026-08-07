{{--
    Alerte sobre : carte nuit-haut, filet gauche de 2 px comme seul accent.
    Pas de vert dans la palette : le succès s'exprime en or clair.
--}}
@props([
    'type' => 'info', {{-- info | succes | erreur --}}
    'titre' => null,
])

@php
    $accent = match ($type) {
        'erreur' => 'border-l-rouge',
        'succes' => 'border-l-or-clair',
        default => 'border-l-ivoire-bas',
    };
@endphp

<div
    role="{{ $type === 'erreur' ? 'alert' : 'status' }}"
    {{ $attributes->merge(['class' => "rounded border border-nuit-bord border-l-2 bg-nuit-haut px-4 py-3 {$accent}"]) }}
>
    @if ($titre)
        <p class="text-16 font-medium text-ivoire">{{ $titre }}</p>
    @endif
    <p class="text-14 text-ivoire-bas {{ $titre ? 'mt-1' : '' }}">{{ $slot }}</p>
</div>
