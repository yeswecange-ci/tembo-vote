<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#12100F">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Mur LED : résolution inconnue → tout le contenu des vues est en vw/vh/clamp.
    Fond nuit pur : un mur LED sur fond clair éblouit et rend les photos illisibles.
--}}
<body class="theme-sombre h-full overflow-hidden bg-nuit font-sans text-ivoire antialiased">
    {{ $slot }}
</body>
</html>
