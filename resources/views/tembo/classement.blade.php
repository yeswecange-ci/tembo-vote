@php
    use App\Enums\Phase;
@endphp

{{--
    Classement invité : le Top 5 dans l'ordre, sans les totaux de votes —
    les chiffres ne vivent que sur le mur LED.
--}}
<x-layouts.guest title="Classement">
    <div class="flex min-h-full flex-col gap-6 py-6">
        <header class="text-center">
            <h1 class="titre text-26 text-ivoire">Classement</h1>
            <p class="mt-2 text-14 text-ivoire-bas">
                @if ($phase === Phase::Frozen)
                    Votes clos, classement figé. La remise du prix se fait sur scène.
                @elseif ($phase === Phase::Reveal)
                    Le gagnant est révélé sur scène en ce moment.
                @elseif ($phase === Phase::Closed)
                    Le Top 5 final de la soirée. Merci d’avoir participé.
                @else
                    Le Top 5 de la soirée. Les compteurs vivent sur l’écran de la salle.
                @endif
            </p>
        </header>

        @if ($top->isEmpty())
            <div class="flex flex-1 flex-col justify-center">
                <x-etat-vide titre="Pas encore de classement">
                    Dès les premiers votes, le Top 5 s’affichera ici.
                    <x-slot:action>
                        <x-bouton variante="secondaire" :href="route('galerie.index')">Voir la galerie</x-bouton>
                    </x-slot:action>
                </x-etat-vide>
            </div>
        @else
            <ol class="space-y-3">
                @foreach ($top as $photo)
                    <li class="flex items-center gap-4 rounded border border-nuit-bord bg-nuit-haut p-3">
                        {{-- Avatar de rang : seule exception autorisée au rounded-full --}}
                        <div @class([
                            'flex size-11 shrink-0 items-center justify-center rounded-full border',
                            'border-or' => $loop->first,
                            'border-nuit-bord' => ! $loop->first,
                        ])>
                            <span @class([
                                'font-mono text-20',
                                'text-or-clair' => $loop->first,
                                'text-ivoire-bas' => ! $loop->first,
                            ])>{{ $loop->iteration }}</span>
                        </div>
                        <img
                            src="{{ $photo->signedImageUrl('vignette') }}"
                            alt="Photo de {{ $photo->display_name }}"
                            class="size-16 shrink-0 rounded bg-nuit object-cover"
                            loading="lazy"
                        >
                        <p class="min-w-0 flex-1 truncate text-16 font-medium text-ivoire">{{ $photo->display_name }}</p>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="mt-auto">
            <x-bouton variante="secondaire" :href="route('galerie.index')" class="min-h-14 w-full">
                Retour à la galerie
            </x-bouton>
        </div>
    </div>
</x-layouts.guest>
