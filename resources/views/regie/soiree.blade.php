@php
    use App\Enums\Phase;
@endphp

{{--
    Pilotage de la soirée : la phase change en un clic, sans confirmation —
    le régisseur est pressé et dans le noir. Le QR d'accès reste scannable
    ici même si le mur LED tombe.
--}}
<x-layouts.admin title="Soirée" actif="regie.soiree" :en-attente="$pendingCount">
    <div class="flex flex-col gap-8">
        @if (session('succes'))
            <x-alerte type="succes">{{ session('succes') }}</x-alerte>
        @endif

        {{-- QR d'accès : le plan B si l'écran de la salle est en panne —
             un invité peut le scanner directement sur ce poste --}}
        <div class="flex flex-col items-center gap-3 rounded border border-nuit-bord bg-nuit-haut p-4 text-center">
            <p class="text-14 text-ivoire-bas">QR d’accès affiché en salle</p>
            <img src="{{ $qr }}" alt="QR code d’accès à la soirée" class="size-48 rounded bg-creme p-2">
            <p class="text-12 text-ivoire-bas">
                valable jusqu’à {{ $accessToken->valid_until->format('H\hi') }} · un nouveau QR toutes les
                {{ config('tembo.access.rotation_minutes') }} min
            </p>
        </div>

        {{-- Phase de la soirée : 6 boutons, un clic, effet immédiat --}}
        <div>
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="titre text-16 text-ivoire">Phase de la soirée</span>
            </h2>

            <div class="mt-4 space-y-3">
                @foreach (Phase::cases() as $phase)
                    <form method="POST" action="{{ route('regie.soiree.phase') }}">
                        @csrf
                        <input type="hidden" name="phase" value="{{ $phase->value }}">
                        <button
                            type="submit"
                            @class([
                                'flex min-h-14 w-full items-center justify-between rounded border px-4 text-16 font-medium transition-opacity hover:opacity-90 active:opacity-70',
                                'border-or bg-nuit-haut text-ivoire' => $phase === $phaseCourante,
                                'border-nuit-bord bg-nuit-haut text-ivoire-bas' => $phase !== $phaseCourante,
                            ])
                        >
                            <span>{{ $phase->label() }}</span>
                            @if ($phase === $phaseCourante)
                                <span class="text-12 text-or-clair">en cours</span>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Repères rapides --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4 text-center">
                <p class="font-mono text-34 text-ivoire">{{ $publishedCount }}</p>
                <p class="text-12 text-ivoire-bas">en ligne</p>
            </div>
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4 text-center">
                <p class="font-mono text-34 text-ivoire">{{ $pendingCount }}</p>
                <p class="text-12 text-ivoire-bas">en attente</p>
            </div>
        </div>
    </div>
</x-layouts.admin>
