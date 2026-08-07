@php
    use App\Enums\PhotoStatus;
@endphp

{{-- Écran 3 du parcours : confirmation et suivi du statut de la photo --}}
<x-layouts.guest title="Ma photo">
    <div class="flex min-h-full flex-col gap-6 py-6">
        <header>
            <h1 class="titre text-26 text-ivoire">Ma photo</h1>
        </header>

        @if (session('photo_envoyee'))
            <x-alerte type="succes" titre="Photo envoyée">
                Votre photo est en cours de validation. Elle apparaîtra dans la galerie d’ici quelques instants.
            </x-alerte>
        @endif

        <div>
            <img
                src="{{ $photo->signedImageUrl('vignette') }}"
                alt="Votre photo, {{ $photo->display_name }}"
                class="w-full rounded border border-nuit-bord"
            >
            <div class="mt-3 flex items-center justify-between gap-4">
                <p class="text-16 font-medium text-ivoire">{{ $photo->display_name }}</p>
                {{-- Statut en pastille : lisible d'un coup d'œil, filet plutôt qu'aplat --}}
                <span @class([
                    'shrink-0 rounded border px-2 py-1 text-12 font-medium',
                    'border-nuit-bord text-ivoire-bas' => $photo->status === PhotoStatus::Pending,
                    'border-or text-or-clair' => $photo->status === PhotoStatus::Approved,
                    'border-rouge text-rouge' => $photo->status === PhotoStatus::Rejected,
                ])>{{ $photo->status->label() }}</span>
            </div>
        </div>

        @if ($photo->status === PhotoStatus::Pending && ! session('photo_envoyee'))
            <x-alerte type="info">
                Votre photo est en cours de validation. Elle apparaîtra dans la galerie d’ici quelques instants.
            </x-alerte>
        @elseif ($photo->status === PhotoStatus::Approved)
            <x-alerte type="succes">
                Votre photo est publiée dans la galerie de la soirée.
            </x-alerte>
        @elseif ($photo->status === PhotoStatus::Rejected)
            <x-alerte type="erreur" titre="Photo refusée">
                Motif : {{ $photo->reject_reason ?? 'non précisé' }}.
                @if ($phase->allowsPublishing())
                    Vous pouvez en publier une nouvelle.
                @endif
            </x-alerte>
        @endif

        <div class="mt-auto space-y-4">
            @if ($photo->status === PhotoStatus::Approved && $phase->allowsVoting())
                <x-bouton :href="route('galerie.index')" class="min-h-14 w-full">Voir la galerie et voter</x-bouton>
            @endif

            <x-bouton variante="secondaire" :href="route('tembo.accueil')" class="min-h-14 w-full">
                Retour à l’accueil
            </x-bouton>

            {{-- Retrait sur demande, en deux appuis : droit de l'invité, à tout moment --}}
            @if ($photo->status !== PhotoStatus::Rejected)
                <form method="POST" action="{{ route('photos.retrait') }}" x-data="{ confirmation: false }" class="text-center">
                    @csrf
                    <button
                        type="button"
                        x-show="!confirmation"
                        x-on:click="confirmation = true"
                        class="min-h-11 text-14 text-ivoire-bas transition-opacity hover:opacity-75 active:opacity-60"
                    >
                        Retirer ma photo de la soirée
                    </button>
                    <button
                        type="submit"
                        x-show="confirmation"
                        x-cloak
                        class="min-h-11 text-14 font-medium text-rouge transition-opacity hover:opacity-90 active:opacity-75"
                    >
                        Confirmer le retrait définitif
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.guest>
