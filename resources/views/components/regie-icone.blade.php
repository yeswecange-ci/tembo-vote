{{--
    Icônes de navigation de la régie : tracés géométriques simples,
    en currentColor pour suivre l'état actif/inactif du lien.
--}}
@props(['nom'])

<svg
    {{ $attributes->merge(['class' => 'size-5 shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($nom)
        @case('tableau')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1" />
            @break

        @case('moderation')
            <path d="M4 6h16" />
            <path d="M4 12h16" />
            <path d="M4 18h8" />
            <path d="M15.5 18.5l2 2 3.5-4" />
            @break

        @case('publiees')
            <rect x="3.5" y="5" width="17" height="14" rx="1" />
            <circle cx="9" cy="10.5" r="1.5" />
            <path d="M20.5 15.5l-4.5-4.5-8 8" />
            @break

        @case('soiree')
            <path d="M4 8h9" /><circle cx="16.5" cy="8" r="2.5" /><path d="M19 8h1" />
            <path d="M4 16h1" /><circle cx="8.5" cy="16" r="2.5" /><path d="M11 16h9" />
            @break

        @case('revelation')
            <path d="M12 4.5l2.3 4.7 5.2.8-3.8 3.7.9 5.2-4.6-2.4-4.6 2.4.9-5.2L4.5 10l5.2-.8z" />
            @break

        @case('sortir')
            <path d="M10 4H5.5v16H10" />
            <path d="M15 8l4 4-4 4" />
            <path d="M19 12H9.5" />
            @break

        @case('menu')
            <path d="M4 6h16" />
            <path d="M4 12h16" />
            <path d="M4 18h16" />
            @break

        @case('fermer')
            <path d="M6 6l12 12" />
            <path d="M18 6L6 18" />
            @break
    @endswitch
</svg>
