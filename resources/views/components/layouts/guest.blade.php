<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover : nécessaire pour respecter la zone sûre des iPhone --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12100F">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-nuit font-sans text-16 text-ivoire antialiased">
    {{-- Grille mobile du brief : marge latérale 20 px, contenu centré au-delà --}}
    <div class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-5">
        <main class="flex-1 py-6">
            {{ $slot }}
        </main>

        <footer class="pb-[max(1rem,env(safe-area-inset-bottom))] pt-6 text-center text-12 text-ivoire-bas">
            {{ config('tembo.legal.responsible_drinking') }}
        </footer>
    </div>

    {{-- Emplacement de la barre de vote fixe (Module 4) --}}
    {{ $barreFixe ?? '' }}
</body>
</html>
