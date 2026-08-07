{{--
    Photos en ligne, avec retrait possible à tout moment : un invité peut
    demander le retrait pendant la soirée (obligation du brief).
    Retrait en deux appuis (le bouton devient « Confirmer ») : assez rapide,
    sans risque de retrait accidentel au pouce.
--}}
<x-layouts.admin title="Publiées" actif="regie.publiees" :en-attente="$pendingCount">
    <div class="flex flex-col gap-6">
        <div class="flex items-baseline justify-between border-b border-nuit-bord pb-4">
            <div>
                <p class="font-mono text-48 text-ivoire">{{ $photos->count() }}</p>
                <p class="text-14 text-ivoire-bas">photo{{ $photos->count() > 1 ? 's' : '' }} en ligne</p>
            </div>
        </div>

        @if (session('collision'))
            <x-alerte type="info">{{ session('collision') }}</x-alerte>
        @endif

        @if (session('succes'))
            <x-alerte type="succes">{{ session('succes') }}</x-alerte>
        @endif

        @if ($photos->isEmpty())
            <x-etat-vide titre="Aucune photo en ligne">
                Les photos validées apparaîtront ici, avec la possibilité de les retirer à tout moment.
            </x-etat-vide>
        @else
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($photos as $photo)
                    <li class="flex flex-col rounded border border-nuit-bord bg-nuit-haut p-3">
                        <img
                            src="{{ $photo->signedImageUrl('vignette') }}"
                            alt="Photo publiée de {{ $photo->display_name }}"
                            class="aspect-square w-full rounded object-cover"
                            loading="lazy"
                        >
                        <p class="mt-2 truncate text-14 font-medium text-ivoire">{{ $photo->display_name }}</p>
                        <p class="text-12 text-ivoire-bas">publiée {{ $photo->moderated_at?->diffForHumans() }}</p>

                        <form
                            method="POST"
                            action="{{ route('regie.photos.retirer', $photo) }}"
                            x-data="{ confirmation: false }"
                            class="mt-3"
                        >
                            @csrf
                            <button
                                type="button"
                                x-show="!confirmation"
                                x-on:click="confirmation = true"
                                class="min-h-11 w-full rounded border border-nuit-bord text-14 font-medium text-ivoire-bas transition-opacity hover:opacity-85 active:opacity-70"
                            >
                                Retirer
                            </button>
                            <button
                                type="submit"
                                x-show="confirmation"
                                x-cloak
                                class="min-h-11 w-full rounded bg-rouge text-14 font-medium text-creme transition-opacity hover:opacity-90 active:opacity-75"
                            >
                                Confirmer le retrait
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.admin>
