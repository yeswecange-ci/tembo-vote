{{--
    Seule impasse possible du parcours invité : on arrive ici sans jeton
    valide (QR périmé, capture d'écran, lien transmis, session expirée).
    Aucun champ, aucun bouton : le seul geste utile est de rescanner l'écran.
--}}
<x-layouts.guest title="Accès à la soirée">
    <div class="flex min-h-full flex-col justify-center gap-8 py-10">
        <div class="flex flex-col items-center gap-6 text-center">
            <x-pastille-logo />
            <div>
                <h1 class="titre text-26 text-ivoire">Soirée Club Tembo</h1>
                <p class="mt-2 text-14 text-ivoire-bas">{{ $message }}</p>
            </div>
        </div>

        <x-alerte type="info">
            Rendez-vous devant l’écran de la salle et scannez le QR code affiché :
            vous entrez directement, sans rien saisir.
        </x-alerte>

        <p class="text-center text-12 text-ivoire-bas">
            Le QR code change toutes les {{ config('tembo.access.rotation_minutes') }} minutes :
            une photo d’écran prise plus tôt ne fonctionne plus.
            En cas de difficulté, adressez-vous au personnel de la soirée.
        </p>
    </div>
</x-layouts.guest>
