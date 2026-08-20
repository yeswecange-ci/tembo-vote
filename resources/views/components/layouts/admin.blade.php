@props([
    'title' => 'Régie',
    'actif' => null,
    'enAttente' => 0,
])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Barre du navigateur au ton du fond global : le thème clair est le défaut --}}
    <meta name="theme-color" content="#FAF7F2">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Coquille d'application de la régie : barre latérale fixe sur desktop,
    tiroir sur mobile (sans transition : les 3 animations du projet sont
    réservées à la galerie, au compteur et à la révélation).
--}}
<body class="min-h-dvh bg-nuit font-sans text-16 text-ivoire antialiased">
    @guest
        {{-- Page de connexion : pas de navigation avant authentification --}}
        <main class="mx-auto w-full max-w-3xl px-5 py-6">
            {{ $slot }}
        </main>
    @else
        <div x-data="{ menuOuvert: false }" class="flex min-h-dvh">

            {{-- Voile de fermeture du tiroir (mobile) --}}
            <div
                x-show="menuOuvert"
                x-cloak
                x-on:click="menuOuvert = false"
                class="fixed inset-0 z-10 bg-nuit/80 lg:hidden"
                aria-hidden="true"
            ></div>

            {{-- Barre latérale --}}
            <aside
                class="fixed inset-y-0 left-0 z-20 flex w-64 -translate-x-full flex-col border-r border-nuit-bord bg-nuit-haut lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
                :class="menuOuvert && 'translate-x-0'"
                aria-label="Navigation de la régie"
            >
                <div class="flex h-16 items-center justify-between border-b border-nuit-bord px-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-full border border-nuit-bord bg-creme">
                            <img src="{{ asset('images/logo-castel.png') }}" alt="" class="w-6">
                        </div>
                        <span class="titre text-14 text-or-clair">Régie Castel</span>
                    </div>
                    <button
                        type="button"
                        class="flex min-h-11 min-w-11 items-center justify-center text-ivoire-bas lg:hidden"
                        x-on:click="menuOuvert = false"
                        aria-label="Fermer le menu"
                    >
                        <x-regie-icone nom="fermer" />
                    </button>
                </div>

                <nav class="flex flex-1 flex-col gap-1 p-3">
                    @foreach ([
                        ['regie.dashboard', 'tableau', 'Tableau de bord'],
                        ['regie.moderation', 'moderation', 'Modération'],
                        ['regie.publiees', 'publiees', 'Publiées'],
                        ['regie.soiree', 'soiree', 'Soirée'],
                        ['regie.revelation', 'revelation', 'Révélation'],
                    ] as [$route, $icone, $libelle])
                        <a
                            href="{{ route($route) }}"
                            @class([
                                'flex min-h-11 items-center gap-3 rounded border-l-2 px-3 text-14 font-medium transition-opacity hover:opacity-85',
                                'border-or bg-nuit text-ivoire' => $actif === $route,
                                'border-transparent text-ivoire-bas' => $actif !== $route,
                            ])
                            @if ($actif === $route) aria-current="page" @endif
                        >
                            <x-regie-icone :nom="$icone" />
                            <span class="flex-1">{{ $libelle }}</span>
                            @if ($route === 'regie.moderation' && $enAttente > 0)
                                <span class="rounded bg-rouge px-1.5 font-mono text-12 text-creme">{{ $enAttente }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-nuit-bord p-3">
                    <p class="px-3 text-12 text-ivoire-bas">
                        Phase : <span class="text-or-clair">{{ \App\Support\EventPhase::current()->label() }}</span>
                    </p>
                    <div class="mt-2 flex items-center justify-between gap-3 px-3">
                        <span class="truncate text-14 text-ivoire">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('regie.deconnexion') }}">
                            @csrf
                            <button
                                type="submit"
                                class="flex min-h-11 items-center gap-2 text-14 text-ivoire-bas transition-opacity hover:opacity-75"
                            >
                                <x-regie-icone nom="sortir" class="size-4" />
                                Sortir
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Contenu --}}
            <div class="flex min-w-0 flex-1 flex-col">
                {{-- En-tête mobile --}}
                <header class="flex h-14 items-center justify-between border-b border-nuit-bord bg-nuit-haut px-4 lg:hidden">
                    <button
                        type="button"
                        class="flex min-h-11 min-w-11 items-center justify-center text-ivoire"
                        x-on:click="menuOuvert = true"
                        aria-label="Ouvrir le menu"
                    >
                        <x-regie-icone nom="menu" />
                    </button>
                    <span class="titre text-14 text-or-clair">{{ $title }}</span>
                    @if ($enAttente > 0)
                        <a href="{{ route('regie.moderation') }}" class="rounded bg-rouge px-1.5 font-mono text-12 text-creme" aria-label="{{ $enAttente }} photos en attente">
                            {{ $enAttente }}
                        </a>
                    @else
                        <span class="min-w-11" aria-hidden="true"></span>
                    @endif
                </header>

                {{-- En-tête desktop --}}
                <header class="hidden h-16 items-center justify-between border-b border-nuit-bord px-8 lg:flex">
                    <h1 class="titre text-20 text-ivoire">{{ $title }}</h1>
                    <p class="text-12 text-ivoire-bas">Soirée Castel Beer Afterwork · 21 août 2026</p>
                </header>

                <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    @endguest
</body>
</html>
