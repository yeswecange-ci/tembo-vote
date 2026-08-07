{{--
    File d'attente de modération : une photo à la fois, la plus ancienne en
    premier. Rafraîchissement automatique toutes les 5 s (polling regie.js).
--}}
<x-layouts.admin title="Modération" actif="regie.moderation" :en-attente="$pendingCount">
    <div
        x-data="moderationEtat"
        data-url="{{ route('regie.etat') }}"
        data-en-attente="{{ $pendingCount }}"
        @if ($photo) data-photo-id="{{ $photo->id }}" @endif
        class="flex flex-col gap-6"
    >
        {{-- Compteur permanent, en gros --}}
        <div class="flex items-baseline justify-between border-b border-nuit-bord pb-4">
            <div>
                <p class="font-mono text-48 text-ivoire" x-text="enAttente">{{ $pendingCount }}</p>
                <p class="text-14 text-ivoire-bas">en attente de validation</p>
            </div>
            <p class="flex items-center gap-2 text-12 text-ivoire-bas" x-show="horsLigne" x-cloak>
                <span class="size-2 rounded-full bg-or-clair" aria-hidden="true"></span>
                Reconnexion…
            </p>
        </div>

        @if (session('collision'))
            <x-alerte type="info">{{ session('collision') }}</x-alerte>
        @endif

        @if (session('succes'))
            <x-alerte type="succes">{{ session('succes') }}</x-alerte>
        @endif

        @if ($photo === null)
            <x-etat-vide titre="Aucune photo en attente">
                Les nouvelles photos apparaîtront ici automatiquement, sans recharger la page.
            </x-etat-vide>
        @else
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                {{-- Vignette dans la file ; l'original s'ouvre à part pour juger la netteté --}}
                <a href="{{ $photo->signedImageUrl('plein') }}" target="_blank" rel="noopener">
                    <img
                        src="{{ $photo->signedImageUrl('vignette') }}"
                        alt="Photo à modérer de {{ $photo->display_name }}"
                        class="w-full rounded"
                    >
                </a>
                <div class="mt-3 flex items-baseline justify-between gap-4">
                    <p class="text-16 font-medium text-ivoire">{{ $photo->display_name }}</p>
                    <p class="text-12 text-ivoire-bas">reçue {{ $photo->created_at->diffForHumans() }}</p>
                </div>
                <p class="mt-1 text-12 text-ivoire-bas">Le prénom fait partie de la modération, au même titre que la photo.</p>
            </div>

            <div x-data="{ refusOuvert: false }" class="flex flex-col gap-8">
                {{-- Deux actions très espacées : pas d'erreur au pouce --}}
                <form method="POST" action="{{ route('regie.photos.valider', $photo) }}">
                    @csrf
                    <input type="hidden" name="verrou" value="{{ $photo->updated_at->toDateTimeString() }}">
                    <x-bouton type="submit" class="min-h-14 w-full">Valider</x-bouton>
                </form>

                <div>
                    <x-bouton
                        type="button"
                        variante="secondaire"
                        class="min-h-14 w-full"
                        x-on:click="refusOuvert = !refusOuvert"
                    >
                        Refuser…
                    </x-bouton>

                    <form
                        method="POST"
                        action="{{ route('regie.photos.refuser', $photo) }}"
                        x-show="refusOuvert"
                        x-cloak
                        class="mt-4 rounded border border-nuit-bord bg-nuit-haut p-4"
                    >
                        @csrf
                        <input type="hidden" name="verrou" value="{{ $photo->updated_at->toDateTimeString() }}">

                        <p class="text-14 font-medium text-ivoire-bas">Motif du refus</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($rejectReasons as $reason)
                                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                                    <input type="radio" name="reason" value="{{ $reason }}" class="size-5 shrink-0 accent-rouge" required>
                                    <span class="text-14 text-ivoire">{{ ucfirst($reason) }}</span>
                                </label>
                            @endforeach
                        </div>

                        @error('reason')
                            <p class="mt-2 text-14 text-rouge">{{ $message }}</p>
                        @enderror

                        <x-bouton type="submit" variante="secondaire" class="mt-4 min-h-14 w-full">
                            Confirmer le refus
                        </x-bouton>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
