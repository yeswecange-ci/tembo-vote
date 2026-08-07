@php
    use App\Enums\Phase;
    use App\Enums\PhotoStatus;

    // Les deux actions reflètent la phase : un bouton inactif explique
    // toujours pourquoi, un écran muet fait abandonner cette cible.
    $notePublication = match (true) {
        $phase->allowsPublishing() => null,
        $phase === Phase::Setup => 'La publication ouvrira au lancement de la soirée.',
        default => 'La publication est terminée pour cette soirée.',
    };

    $noteVote = match (true) {
        $phase->allowsVoting() => null,
        $phase === Phase::Setup => 'Le vote ouvrira pendant la soirée.',
        default => 'Les votes sont clos.',
    };
@endphp

<x-layouts.guest title="Bienvenue">
    <div class="flex min-h-full flex-col gap-8 py-6">
        {{-- En-tête de la soirée --}}
        <header class="flex flex-col items-center gap-5 pt-4 text-center">
            <x-pastille-logo />
            <div>
                <h1 class="titre text-26 text-ivoire">Soirée Club Tembo</h1>
                <p class="mt-2 font-mono text-14 text-or-clair">14 août 2026 · Kinshasa</p>
            </div>
            <p class="max-w-xs text-14 text-ivoire-bas">
                Un selfie avec votre Tembo, un vote, un gagnant révélé sur scène.
            </p>
        </header>

        {{-- Les deux actions --}}
        <div class="space-y-6">
            <div>
                @if ($photo !== null)
                    {{-- Une seule photo par invité : le bouton devient « Ma photo » et montre son statut --}}
                    <x-bouton :href="route('photos.create')" class="min-h-14 w-full">Ma photo</x-bouton>
                    <p @class([
                        'mt-3 text-center text-12',
                        'text-ivoire-bas' => $photo->status !== PhotoStatus::Rejected,
                        'text-rouge' => $photo->status === PhotoStatus::Rejected,
                    ])>{{ $photo->status->label() }}</p>
                @elseif ($phase->allowsPublishing())
                    <x-bouton :href="route('photos.create')" class="min-h-14 w-full">Publier ma photo</x-bouton>
                @else
                    <x-bouton class="min-h-14 w-full" disabled>Publier ma photo</x-bouton>
                    <p class="mt-3 text-center text-12 text-ivoire-bas">{{ $notePublication }}</p>
                @endif
            </div>

            <div>
                @if ($phase->allowsVoting())
                    <x-bouton variante="secondaire" :href="route('galerie.index')" class="min-h-14 w-full">
                        {{ $vote !== null ? 'Voir la galerie' : 'Voter' }}
                    </x-bouton>
                    @if ($publishedCount > 0)
                        <p class="mt-3 text-center text-12 text-ivoire-bas">
                            <span class="font-mono text-ivoire">{{ $publishedCount }}</span>
                            photo{{ $publishedCount > 1 ? 's' : '' }} déjà dans la galerie
                        </p>
                    @endif
                @else
                    <x-bouton variante="secondaire" class="min-h-14 w-full" disabled>Voter</x-bouton>
                    <p class="mt-3 text-center text-12 text-ivoire-bas">{{ $noteVote }}</p>
                @endif
            </div>
        </div>

        {{-- Votre soirée : où en est l'invité, en images et d'un coup d'œil --}}
        @if ($photo !== null || $vote !== null)
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <h2 class="text-14 font-medium text-ivoire-bas">Votre soirée</h2>
                <div class="mt-3 space-y-3">
                    @if ($photo !== null)
                        <a
                            href="{{ route('photos.create') }}"
                            class="flex min-h-11 items-center gap-3 rounded border border-nuit-bord p-2 transition-opacity hover:opacity-85 active:opacity-70"
                        >
                            <img
                                src="{{ $photo->signedImageUrl('vignette') }}"
                                alt="Votre photo"
                                class="size-12 shrink-0 rounded bg-nuit object-cover"
                            >
                            <span class="min-w-0 flex-1">
                                <span class="block text-14 font-medium text-ivoire">Ma photo</span>
                                <span class="block truncate text-12 text-ivoire-bas">{{ $photo->display_name }}</span>
                            </span>
                            <span @class([
                                'shrink-0 rounded border px-2 py-1 text-12 font-medium',
                                'border-nuit-bord text-ivoire-bas' => $photo->status === PhotoStatus::Pending,
                                'border-or text-or-clair' => $photo->status === PhotoStatus::Approved,
                                'border-rouge text-rouge' => $photo->status === PhotoStatus::Rejected,
                            ])>{{ $photo->status->label() }}</span>
                        </a>
                    @endif

                    @if ($vote !== null)
                        <a
                            href="{{ route('galerie.index') }}"
                            class="flex min-h-11 items-center gap-3 rounded border border-nuit-bord p-2 transition-opacity hover:opacity-85 active:opacity-70"
                        >
                            <img
                                src="{{ $vote->signedImageUrl('vignette') }}"
                                alt="Photo de {{ $vote->display_name }}"
                                class="size-12 shrink-0 rounded bg-nuit object-cover"
                            >
                            <span class="min-w-0 flex-1">
                                <span class="block text-14 font-medium text-ivoire">Mon vote</span>
                                <span class="block truncate text-12 text-ivoire-bas">{{ $vote->display_name }}</span>
                            </span>
                            @if ($phase->allowsVoting())
                                <span class="shrink-0 text-12 text-ivoire-bas">Changer ›</span>
                            @endif
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Comment ça marche : trois temps, zéro jargon --}}
        <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
            <h2 class="text-14 font-medium text-ivoire-bas">Comment ça marche</h2>
            <ol class="mt-3 space-y-4">
                <li class="flex gap-4">
                    <span class="font-mono text-20 leading-none text-or-clair">1</span>
                    <div>
                        <p class="text-14 font-medium text-ivoire">Prenez un selfie avec votre Tembo</p>
                        <p class="mt-1 text-12 text-ivoire-bas">Depuis votre téléphone, en un geste — la photo est compressée automatiquement.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="font-mono text-20 leading-none text-or-clair">2</span>
                    <div>
                        <p class="text-14 font-medium text-ivoire">Publiez-la dans la galerie</p>
                        <p class="mt-1 text-12 text-ivoire-bas">Validée en quelques instants. Une seule photo par invité — et retirable à tout moment.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="font-mono text-20 leading-none text-or-clair">3</span>
                    <div>
                        <p class="text-14 font-medium text-ivoire">Votez pour la photo de la soirée</p>
                        <p class="mt-1 text-12 text-ivoire-bas">Un appui suffit, vous pouvez changer d’avis. Le gagnant est révélé sur scène.</p>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</x-layouts.guest>
