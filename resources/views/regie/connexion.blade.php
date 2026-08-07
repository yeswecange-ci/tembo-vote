<x-layouts.admin title="Connexion">
    <div class="mx-auto flex min-h-full max-w-md flex-col justify-center gap-8 py-10">
        <div class="flex flex-col items-center gap-6 text-center">
            <x-pastille-logo />
            <div>
                <h1 class="titre text-26 text-ivoire">Régie</h1>
                <p class="mt-3 text-14 text-ivoire-bas">Accès réservé aux modérateurs de la soirée.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('regie.connexion.verifier') }}" class="space-y-6">
            @csrf

            <x-champ
                label="Adresse e-mail"
                name="email"
                type="email"
                autocomplete="username"
                required
                autofocus
                value="{{ old('email') }}"
                :erreur="$errors->first('email')"
            />

            <x-champ
                label="Mot de passe"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                :erreur="$errors->first('password')"
            />

            <x-bouton type="submit" class="min-h-14 w-full">Se connecter</x-bouton>
        </form>
    </div>
</x-layouts.admin>
