@php
    use App\Enums\Phase;
@endphp

{{-- État conçu : publication fermée (avant ou après la fenêtre de la soirée) --}}
<x-layouts.guest title="Publier ma photo">
    <div class="flex min-h-full flex-col justify-center py-10">
        <x-etat-vide :titre="$phase === Phase::Setup ? 'La publication n’est pas encore ouverte' : 'La publication est terminée'">
            @if ($phase === Phase::Setup)
                Revenez au lancement de la soirée pour publier votre selfie avec votre Tembo.
            @else
                Merci d’avoir participé à la soirée Club Tembo.
            @endif
            <x-slot:action>
                <x-bouton variante="secondaire" :href="route('tembo.accueil')">Retour à l’accueil</x-bouton>
            </x-slot:action>
        </x-etat-vide>
    </div>
</x-layouts.guest>
