{{-- Vue d'ensemble : les chiffres qui comptent, le PIN, la phase, l'activité --}}
<x-layouts.admin title="Tableau de bord" actif="regie.dashboard" :en-attente="$pendingCount">
    <div class="flex flex-col gap-6">

        @if ($pendingCount > 0)
            <x-alerte type="info" titre="{{ $pendingCount }} photo{{ $pendingCount > 1 ? 's' : '' }} en attente de validation">
                Les invités attendent : chaque minute compte.
            </x-alerte>
        @endif

        {{-- Chiffres clés --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <a href="{{ route('regie.moderation') }}" class="rounded border {{ $pendingCount > 0 ? 'border-rouge' : 'border-nuit-bord' }} bg-nuit-haut p-4 transition-opacity hover:opacity-85">
                <p class="font-mono text-48 text-ivoire">{{ $pendingCount }}</p>
                <p class="text-12 text-ivoire-bas">en attente</p>
            </a>
            <a href="{{ route('regie.publiees') }}" class="rounded border border-nuit-bord bg-nuit-haut p-4 transition-opacity hover:opacity-85">
                <p class="font-mono text-48 text-ivoire">{{ $approvedCount }}</p>
                <p class="text-12 text-ivoire-bas">en ligne</p>
            </a>
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="font-mono text-48 text-ivoire">{{ $votesCount }}</p>
                <p class="text-12 text-ivoire-bas">votes</p>
            </div>
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="font-mono text-48 text-ivoire">{{ $sessionsCount }}</p>
                <p class="text-12 text-ivoire-bas">invités connectés</p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            {{-- PIN courant --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4 text-center">
                <p class="text-14 text-ivoire-bas">Code d’accès affiché en salle</p>
                <p class="font-mono text-48 text-or-clair">{{ $pin->code }}</p>
                <p class="text-12 text-ivoire-bas">
                    valable jusqu’à {{ $pin->valid_until->format('H\hi') }} ·
                    nouveau code toutes les {{ config('tembo.pin.rotation_minutes') }} min
                </p>
            </div>

            {{-- Phase courante --}}
            <div class="flex flex-col justify-between gap-4 rounded border border-nuit-bord bg-nuit-haut p-4">
                <div>
                    <p class="text-14 text-ivoire-bas">Phase de la soirée</p>
                    <p class="titre mt-1 text-20 text-ivoire">{{ $phaseCourante->label() }}</p>
                </div>
                <x-bouton variante="secondaire" :href="route('regie.soiree')" class="w-full">
                    Piloter la soirée
                </x-bouton>
            </div>
        </div>

        {{-- Dernières actions --}}
        <div class="rounded border border-nuit-bord bg-nuit-haut">
            <h2 class="border-b border-nuit-bord px-4 py-3 text-14 font-medium text-ivoire-bas">Dernières actions</h2>
            @if ($dernieresActions->isEmpty())
                <p class="px-4 py-6 text-center text-14 text-ivoire-bas">
                    Aucune action pour l’instant. Tout ce que fait la régie s’affichera ici.
                </p>
            @else
                <ul>
                    @foreach ($dernieresActions as $action)
                        <li class="flex items-baseline justify-between gap-4 border-b border-nuit-bord px-4 py-3 last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate text-14 text-ivoire">{{ $action->actionLabel() }}</p>
                                <p class="text-12 text-ivoire-bas">par {{ $action->actor }}</p>
                            </div>
                            <p class="shrink-0 font-mono text-12 text-ivoire-bas">{{ $action->created_at->format('H\hi') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Export de fin de soirée : ZIP des photos validées + CSV --}}
        <a
            href="{{ route('regie.export') }}"
            class="flex min-h-14 w-full items-center justify-center rounded border border-nuit-bord bg-nuit-haut text-16 font-medium text-ivoire transition-opacity hover:opacity-85 active:opacity-70"
        >
            Exporter les photos validées (ZIP + CSV)
        </a>

        {{-- Refusées : discret, pour information --}}
        <p class="text-center text-12 text-ivoire-bas">{{ $rejectedCount }} photo{{ $rejectedCount > 1 ? 's' : '' }} refusée{{ $rejectedCount > 1 ? 's' : '' }} depuis le début de la soirée</p>
    </div>
</x-layouts.admin>
