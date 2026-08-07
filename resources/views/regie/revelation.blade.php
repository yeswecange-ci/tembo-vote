@php
    use App\Enums\Phase;
@endphp

{{--
    Relecture humaine du Top 5 avant la remise du prix — c'est la vraie
    protection anti-triche du dispositif. Enchaînement guidé :
    Votes clos → vérifier → valider → lancer la révélation → remerciement.
--}}
<x-layouts.admin title="Révélation" actif="regie.revelation" :en-attente="$pendingCount">
    <div class="flex flex-col gap-6">

        @if (session('succes'))
            <x-alerte type="succes">{{ session('succes') }}</x-alerte>
        @endif

        @if (session('erreur'))
            <x-alerte type="erreur">{{ session('erreur') }}</x-alerte>
        @endif

        {{-- Étape 1 : figer les votes --}}
        @if (! in_array($phaseCourante, [Phase::Frozen, Phase::Reveal, Phase::Closed]))
            <x-alerte type="info" titre="Le classement bouge encore">
                Les votes sont ouverts (phase « {{ $phaseCourante->label() }} »). Figez-les avant de valider le classement final.
            </x-alerte>
            <form method="POST" action="{{ route('regie.soiree.phase') }}">
                @csrf
                <input type="hidden" name="phase" value="{{ Phase::Frozen->value }}">
                <x-bouton type="submit" variante="secondaire" class="min-h-14 w-full">
                    Passer en « Votes clos »
                </x-bouton>
            </form>
        @endif

        {{-- Top 5 avec votes suspects signalés --}}
        <div>
            <h2 class="flex items-baseline justify-between gap-3 border-b border-nuit-bord pb-2">
                <span class="titre text-16 text-ivoire">Top 5 final</span>
                <span class="text-12 text-ivoire-bas">{{ $phaseCourante->label() }}</span>
            </h2>

            @if ($top->isEmpty())
                <div class="mt-4">
                    <x-etat-vide titre="Aucune photo en ligne">
                        Le classement apparaîtra dès qu'une photo sera publiée et votée.
                    </x-etat-vide>
                </div>
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($top as $photo)
                        <li class="flex items-center gap-4 rounded border border-nuit-bord bg-nuit-haut p-3">
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
                            >

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-16 font-medium text-ivoire">{{ $photo->display_name }}</p>
                                @if (($suspectCounts[$photo->id] ?? 0) > 0)
                                    <p class="text-12 text-rouge">{{ $suspectCounts[$photo->id] }} vote{{ $suspectCounts[$photo->id] > 1 ? 's' : '' }} suspect{{ $suspectCounts[$photo->id] > 1 ? 's' : '' }}</p>
                                @else
                                    <p class="text-12 text-ivoire-bas">aucun vote suspect</p>
                                @endif
                            </div>

                            <p class="shrink-0 text-right">
                                <span class="block font-mono text-26 leading-none text-ivoire">{{ $photo->votes_count }}</span>
                                <span class="text-12 text-ivoire-bas">votes</span>
                            </p>
                        </li>
                    @endforeach
                </ol>

                <p class="mt-3 text-12 text-ivoire-bas">
                    Suspect = plusieurs votes émis depuis la même empreinte d'appareil (même modèle + même réseau
                    peuvent se confondre). Rien n'est bloqué automatiquement : c'est votre relecture qui fait foi.
                    Pour disqualifier une photo, retirez-la depuis « Publiées », le classement se recalcule.
                </p>
            @endif
        </div>

        {{-- Étapes 2 et 3 : valider, puis lancer --}}
        @if ($validatedAt !== null && $validatedWinner !== null)
            <x-alerte type="succes" titre="Classement validé">
                Gagnant : {{ $validatedWinner->display_name }} — validé à {{ $validatedAt->format('H\hi') }} par {{ $validatedBy }}.
            </x-alerte>

            @if ($top->isNotEmpty() && $top->first()->id !== $validatedWinner->id)
                <x-alerte type="erreur" titre="Le classement a changé depuis la validation">
                    Le premier actuel ({{ $top->first()->display_name }}) n'est plus le gagnant validé
                    ({{ $validatedWinner->display_name }}). Revalidez avant de lancer la révélation.
                </x-alerte>
            @endif
        @endif

        <div class="space-y-4">
            @if (in_array($phaseCourante, [Phase::Frozen]))
                <form method="POST" action="{{ route('regie.revelation.valider') }}">
                    @csrf
                    <x-bouton type="submit" variante="{{ $validatedAt === null ? 'primaire' : 'secondaire' }}" class="min-h-14 w-full">
                        {{ $validatedAt === null ? 'Valider le classement final' : 'Revalider le classement' }}
                    </x-bouton>
                </form>
            @endif

            @if ($validatedAt !== null && $phaseCourante === Phase::Frozen)
                <form method="POST" action="{{ route('regie.revelation.lancer') }}">
                    @csrf
                    <x-bouton type="submit" class="min-h-14 w-full">Lancer la révélation sur l'écran</x-bouton>
                </form>
            @endif

            @if ($phaseCourante === Phase::Reveal)
                <x-alerte type="info">
                    La révélation est affichée sur l'écran. Quand le moment de scène est terminé,
                    passez à l'écran de remerciement.
                </x-alerte>
                <form method="POST" action="{{ route('regie.soiree.phase') }}">
                    @csrf
                    <input type="hidden" name="phase" value="{{ Phase::Closed->value }}">
                    <x-bouton type="submit" variante="secondaire" class="min-h-14 w-full">
                        Terminer — écran de remerciement
                    </x-bouton>
                </form>
            @endif
        </div>
    </div>
</x-layouts.admin>
