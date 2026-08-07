{{--
    Gabarit commun des pages d'erreur : sobre, en français, avec une action.
    Jamais de « Une erreur est survenue » sans consigne.
--}}
@props(['code', 'titre'])

<x-layouts.guest :title="$titre">
    <div class="flex min-h-full flex-col items-center justify-center gap-6 py-16 text-center">
        <x-pastille-logo />
        <div>
            <p class="font-mono text-14 text-ivoire-bas">{{ $code }}</p>
            <h1 class="titre mt-2 text-26 text-ivoire">{{ $titre }}</h1>
        </div>
        <p class="max-w-xs text-14 text-ivoire-bas">{{ $slot }}</p>
        <x-bouton variante="secondaire" href="/tembo">Revenir à la soirée</x-bouton>
    </div>
</x-layouts.guest>
