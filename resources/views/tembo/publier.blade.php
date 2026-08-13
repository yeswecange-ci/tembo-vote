{{--
    Parcours de publication — 3 écrans maximum du QR à la photo envoyée :
    1. capture (+ aperçu), 2. nom + consentements, 3. confirmation (page « Ma photo »).
    Les étapes 1 et 2 vivent côté client (Alpine) : l'état survit aux à-coups réseau.
--}}
<x-layouts.guest title="Publier ma photo">
    <div
        x-data="publicationPhoto()"
        data-url="{{ route('photos.store') }}"
        class="flex min-h-full flex-col gap-8 py-6"
    >
        <header>
            {{-- Repère d'avancement : l'invité sait toujours où il en est --}}
            <p class="font-mono text-12 text-or-clair">Étape <span x-text="etape === 'details' ? '2' : '1'">1</span> / 2</p>
            <h1 class="titre mt-2 text-26 text-ivoire">Votre photo</h1>
            <p class="mt-3 text-14 text-ivoire-bas" x-text="etape === 'details'
                ? 'Le prénom affiché et votre accord, puis c’est envoyé.'
                : 'Un selfie avec votre Tembo — une seule photo par invité, choisissez la bonne.'">
                Un selfie avec votre Tembo — une seule photo par invité, choisissez la bonne.
            </p>
        </header>

        @if (session('photo_retiree'))
            <x-alerte type="succes" titre="Photo retirée">
                Votre photo n’apparaît plus dans la galerie ni au classement.
            </x-alerte>
        @elseif ($photoRefusee !== null)
            <x-alerte type="erreur" titre="Votre photo précédente a été refusée">
                Motif : {{ $photoRefusee->reject_reason ?? 'non précisé' }}. Vous pouvez en publier une nouvelle.
            </x-alerte>
        @endif

        <noscript>
            <x-alerte type="erreur" titre="JavaScript est désactivé">
                Activez JavaScript dans votre navigateur pour publier votre photo :
                la compression se fait sur votre téléphone.
            </x-alerte>
        </noscript>

        <template x-if="erreurGlobale">
            <x-alerte type="erreur"><span x-text="erreurGlobale"></span></x-alerte>
        </template>

        {{-- Étape 1a — capture --}}
        <div x-show="etape === 'capture' && !apercu" class="flex flex-1 flex-col justify-center gap-4">
            <label class="flex min-h-14 w-full cursor-pointer select-none items-center justify-center rounded bg-rouge px-6 text-16 font-medium text-creme transition-opacity hover:opacity-90 active:opacity-75 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-or-clair">
                Prendre un selfie
                <input
                    type="file"
                    accept="image/*"
                    capture="user"
                    class="sr-only"
                    x-on:change="choisirFichier($event)"
                >
            </label>

            <label class="flex min-h-14 w-full cursor-pointer select-none items-center justify-center rounded border border-nuit-bord bg-nuit-haut px-6 text-16 font-medium text-ivoire transition-opacity hover:opacity-85 active:opacity-70 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-or-clair">
                Choisir dans la galerie
                <input
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    x-on:change="choisirFichier($event)"
                >
            </label>
        </div>

        {{-- Étape 1b — aperçu, possibilité de reprendre --}}
        <div x-show="etape === 'capture' && apercu" x-cloak class="flex flex-1 flex-col gap-6">
            <img :src="apercu" alt="Aperçu de votre photo" class="w-full rounded border border-nuit-bord">

            <div class="flex flex-col gap-4">
                <x-bouton class="min-h-14 w-full" x-on:click="etape = 'details'">Continuer</x-bouton>
                <x-bouton variante="discret" class="w-full" x-on:click="reprendre()">Reprendre la photo</x-bouton>
            </div>
        </div>

        {{-- Étape 2 — nom + consentements + envoi --}}
        <div x-show="etape === 'details'" x-cloak class="flex flex-1 flex-col gap-6">
            <div class="flex items-center gap-4">
                <img :src="apercu" alt="Aperçu de votre photo" class="size-16 shrink-0 rounded border border-nuit-bord object-cover">
                <button type="button" class="min-h-11 text-14 text-ivoire-bas transition-opacity hover:opacity-75" x-on:click="reprendre()">
                    Changer de photo
                </button>
            </div>

            <template x-if="premiereErreur('photo')">
                <x-alerte type="erreur"><span x-text="premiereErreur('photo')"></span></x-alerte>
            </template>

            <div>
                <label for="display_name" class="mb-2 block text-14 font-medium text-ivoire-bas">Prénom ou pseudo</label>
                <input
                    id="display_name"
                    type="text"
                    maxlength="24"
                    autocomplete="given-name"
                    placeholder="Aïcha"
                    x-model="nom"
                    class="block min-h-11 w-full rounded border bg-nuit-haut px-4 text-16 text-ivoire placeholder:text-ivoire-bas"
                    :class="premiereErreur('display_name') ? 'border-rouge' : 'border-nuit-bord'"
                >
                <p class="mt-2 text-14 text-rouge" x-show="premiereErreur('display_name')" x-cloak x-text="premiereErreur('display_name')"></p>
                <p class="mt-2 text-12 text-ivoire-bas" x-show="!premiereErreur('display_name')">2 à 24 caractères, affiché sous votre photo.</p>
            </div>

            {{-- Le consentement est donné par l'envoi : la mention reste lue
                 avant le geste, sans case à cocher qui freine le parcours. --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="text-14 text-ivoire">{{ config('tembo.legal.consent_event') }}</p>
            </div>

            <p class="text-12 text-ivoire-bas">{{ config('tembo.legal.privacy_notice') }}</p>

            <div class="mt-auto space-y-4">
                {{-- Barre de progression réelle pendant l'envoi --}}
                <div x-show="envoiEnCours" x-cloak>
                    <div class="flex items-baseline justify-between">
                        <p class="text-14 text-ivoire">Envoi de la photo…</p>
                        <p class="font-mono text-14 text-or-clair" x-text="progression + ' %'"></p>
                    </div>
                    <div class="mt-2 h-1 w-full bg-nuit-bord">
                        <div class="h-1 bg-rouge" :style="'width:' + progression + '%'"></div>
                    </div>
                </div>

                <x-bouton class="min-h-14 w-full" x-on:click="envoyer()" x-bind:disabled="envoiEnCours">
                    Envoyer ma photo
                </x-bouton>
            </div>
        </div>
    </div>
</x-layouts.guest>
