<x-layouts.guest title="Accès à la soirée">
    <div class="flex min-h-full flex-col justify-center gap-8 py-10">
        <div class="flex flex-col items-center gap-6 text-center">
            <x-pastille-logo />
            <div>
                <h1 class="titre text-26 text-ivoire">Soirée Club Tembo</h1>
                <p class="mt-2 text-14 text-ivoire-bas">
                    Saisissez le code à 4 chiffres affiché sur l’écran de la salle.
                </p>
            </div>
        </div>

        @if (session('message'))
            <x-alerte type="info">{{ session('message') }}</x-alerte>
        @endif

        {{-- Envoi automatique dès le 4e chiffre : zéro friction. Le bouton
             reste là pour le repli sans JavaScript. --}}
        <form
            method="POST"
            action="{{ route('tembo.pin.verifier') }}"
            class="space-y-6"
            x-data
        >
            @csrf

            {{-- Grand format : c'est LE geste de la soirée, il doit se faire d'un pouce, dans le noir --}}
            <x-champ
                label="Code d’accès"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="4"
                placeholder="····"
                required
                autofocus
                class="min-h-16 text-center font-mono text-34 tracking-widest"
                x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 4); if ($el.value.length === 4) $el.form.requestSubmit()"
                :erreur="$errors->first('code')"
            />

            <x-bouton type="submit" class="w-full">Entrer</x-bouton>
        </form>

        <p class="text-center text-12 text-ivoire-bas">
            Plus rapide : scannez le QR code affiché sur l’écran de la salle — vous entrez sans rien saisir.
            Le code change régulièrement ; en cas de difficulté, adressez-vous au personnel.
        </p>
    </div>
</x-layouts.guest>
