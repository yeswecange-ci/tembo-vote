{{--
    Galerie 2 colonnes : un appui sur une photo = un vote (changeable).
    Les totaux de votes ne sont jamais affichés ici — anti effet de meute,
    les chiffres ne vivent que sur le mur LED.
--}}
<x-layouts.guest title="Galerie">
    <div
        x-data="galerie"
        data-url-api="{{ route('api.galerie') }}"
        data-url-vote="{{ route('votes.store') }}"
        data-initial="{{ json_encode([
            'photos' => $photosInitiales,
            'complet' => $complet,
            'monVote' => $monVote,
            'monVoteNom' => $monVoteNom,
            'maPhotoId' => $maPhotoId,
            'peutVoter' => $phase->allowsVoting(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        class="flex min-h-full flex-col gap-6 pb-24"
    >
        <header class="flex items-baseline justify-between gap-4">
            <div>
                <h1 class="titre text-26 text-ivoire">Galerie</h1>
                {{-- x-text : la consigne suit la phase, que le polling rafraîchit toutes les 3 s --}}
                <p class="mt-2 text-14 text-ivoire-bas" x-text="texteEntete()">
                    @if ($phase->allowsVoting())
                        Touchez une photo pour voter. Vous pouvez changer d’avis à tout moment.
                    @else
                        Les votes sont fermés pour le moment.
                    @endif
                </p>
            </div>
            <p class="flex shrink-0 items-center gap-2 text-12 text-ivoire-bas" x-show="horsLigne" x-cloak>
                <span class="size-2 rounded-full bg-or-clair" aria-hidden="true"></span>
                Reconnexion…
            </p>
        </header>

        <template x-if="erreurGlobale">
            <x-alerte type="erreur"><span x-text="erreurGlobale"></span></x-alerte>
        </template>

        {{-- Recherche par prénom : filtre instantané sur toute la galerie --}}
        <div>
            <label for="recherche" class="sr-only">Rechercher un prénom</label>
            <input
                id="recherche"
                type="search"
                placeholder="Rechercher un prénom…"
                autocomplete="off"
                x-model="recherche"
                x-on:input="chargerCatalogue()"
                class="block min-h-11 w-full rounded border border-nuit-bord bg-nuit-haut px-4 text-16 text-ivoire placeholder:text-ivoire-bas focus:border-or-clair"
            >
        </div>

        {{-- Recherche sans résultat : un état conçu, pas un trou --}}
        <div x-show="photos.length && recherche.trim() && !photosVisibles().length" x-cloak class="flex flex-1 flex-col justify-center">
            <x-etat-vide titre="Aucune photo à ce prénom">
                Vérifiez l’orthographe, ou effacez la recherche pour revoir toute la galerie.
            </x-etat-vide>
        </div>

        {{-- État vide conçu : une invitation à agir, pas un trou --}}
        <div x-show="!photos.length" x-cloak class="flex flex-1 flex-col justify-center">
            <x-etat-vide titre="Aucune photo pour l’instant">
                Les photos validées apparaissent ici en direct, sans recharger la page.
                @if ($phase->allowsPublishing() && $maPhotoId === null)
                    <x-slot:action>
                        <x-bouton :href="route('photos.create')">Publier la première photo</x-bouton>
                    </x-slot:action>
                @endif
            </x-etat-vide>
        </div>

        <ul class="grid grid-cols-2 gap-3" x-show="photosVisibles().length">
            <template x-for="photo in photosVisibles()" :key="photo.id">
                <li :class="nouveaux[photo.id] ? 'animate-apparition' : ''">
                    <button
                        type="button"
                        class="block w-full overflow-hidden rounded text-left transition-opacity hover:opacity-90 active:opacity-75"
                        :class="monVote === photo.id ? 'border-2 border-rouge' : 'border border-nuit-bord'"
                        :disabled="!peutVoter"
                        :aria-pressed="monVote === photo.id"
                        x-on:click="voter(photo.id)"
                    >
                        <img
                            :src="photo.vignette"
                            :alt="'Photo de ' + photo.nom"
                            class="aspect-square w-full bg-nuit-haut object-cover"
                            loading="lazy"
                        >
                        <span class="flex min-h-9 items-center justify-between gap-2 bg-nuit-haut px-3 py-2">
                            <span class="truncate text-14 font-medium text-ivoire" x-text="photo.nom"></span>
                            <span x-show="monVote === photo.id" x-cloak class="shrink-0 text-12 font-medium text-rouge">Mon vote</span>
                        </span>
                    </button>
                </li>
            </template>
        </ul>

        {{-- Sentinelle du défilement infini --}}
        <div x-ref="sentinelle" class="h-1" aria-hidden="true"></div>
        <p x-show="chargementAncien" x-cloak class="text-center text-12 text-ivoire-bas">Chargement…</p>

        {{-- Confirmation de vote : message nominatif, 2,5 s, au-dessus de la barre --}}
        <div
            x-show="messageVote"
            x-cloak
            class="pointer-events-none fixed inset-x-0 bottom-24 z-20 flex justify-center px-5"
            role="status"
        >
            <p class="rounded border border-or bg-nuit-haut px-4 py-2 text-14 font-medium text-ivoire" x-text="messageVote"></p>
        </div>

        {{--
            Barre fixe : rappel du vote + accès au classement, ruban rouge en
            bord supérieur. En thème sombre : la barre se détache nettement de
            la page claire et ancre le bas de l'écran.
        --}}
        <div class="theme-sombre fixed inset-x-0 bottom-0 z-10">
            <x-ruban variante="plein" />
            {{-- pb en zone sûre : la barre ne se glisse jamais sous la barre système iPhone --}}
            <div class="bg-nuit-haut pb-[env(safe-area-inset-bottom)]">
                <div class="mx-auto flex w-full max-w-md items-center justify-between gap-4 px-5 py-3">
                    <div class="min-w-0">
                        <p
                            class="text-12"
                            :class="confirmationVote ? 'text-or-clair' : 'text-ivoire-bas'"
                            x-text="confirmationVote ? 'Vote enregistré ✓' : (monVote ? 'Mon vote' : 'Galerie')"
                        ></p>
                        <p class="truncate text-16 font-medium text-ivoire" x-text="texteBarre()">
                            {{ $monVoteNom ?? ($phase->allowsVoting() ? 'Touchez une photo pour voter' : 'Votes fermés') }}
                        </p>
                    </div>
                    <x-bouton variante="secondaire" :href="route('classement')" class="shrink-0">Classement</x-bouton>
                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
