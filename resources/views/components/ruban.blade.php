{{--
    Ruban Castel — le bandeau à bords ondulés du logo, repris comme motif
    structurel. Seul écart décoratif autorisé du projet, à exactement
    deux endroits : sous le titre du Mode Écran (filet or) et en bord
    supérieur de la barre de vote mobile (plein rouge).
--}}
@props([
    'variante' => 'filet', {{-- filet (trait or) | plein (aplat rouge) --}}
])

@if ($variante === 'plein')
    <svg
        {{ $attributes->merge(['class' => 'block h-2 w-full text-rouge']) }}
        viewBox="0 0 120 8"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <path d="M0 5 Q 15 1, 30 5 T 60 5 T 90 5 T 120 5 L 120 8 L 0 8 Z" fill="currentColor" />
    </svg>
@else
    <svg
        {{ $attributes->merge(['class' => 'block h-2 w-full text-or']) }}
        viewBox="0 0 120 8"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <path
            d="M0 4 Q 15 1, 30 4 T 60 4 T 90 4 T 120 4"
            fill="none"
            stroke="currentColor"
            stroke-width="1"
            vector-effect="non-scaling-stroke"
        />
    </svg>
@endif
