{{--
    Démonstration du design system — Module 0.
    Page statique, servie uniquement en environnement local, jamais déployée.
    Les données visibles ici sont des exemples d'affichage, pas du contenu réel.
--}}
<x-layouts.guest title="Design system">
    <div class="space-y-12">

        {{-- ================= En-tête ================= --}}
        <header class="space-y-6 pt-4">
            <x-pastille-logo />

            <div>
                <p class="font-mono text-12 text-or-clair">Module 0 · page locale uniquement</p>
                <h1 class="titre mt-2 text-34 text-ivoire">Design system</h1>
                <p class="mt-2 text-14 text-ivoire-bas">
                    Tout ce qui est à l'écran vient des tokens : couleurs, échelle typographique,
                    espacements multiples de 4. Rien d'autre n'existe.
                </p>
            </div>
        </header>

        {{-- ================= 01 · Palette ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">01</span>
                <span class="titre text-16 text-ivoire">Palette</span>
            </h2>

            @php
                // Thème clair (l'écran LED garde le sombre via .theme-sombre)
                $couleurs = [
                    ['nuit', '#FAF7F2', 'bg-nuit', 'fond global'],
                    ['nuit-haut', '#FFFFFF', 'bg-nuit-haut', 'cartes, surfaces élevées'],
                    ['nuit-bord', '#E7E1D6', 'bg-nuit-bord', 'bordures, séparateurs 1 px'],
                    ['ivoire', '#1F1B18', 'bg-ivoire', 'texte principal'],
                    ['ivoire-bas', '#6E675E', 'bg-ivoire-bas', 'texte secondaire'],
                    ['rouge', '#C8161D', 'bg-rouge', "accent d'action, exclusivement"],
                    ['or', '#8C734B', 'bg-or', 'filets et cadres — jamais en texte'],
                    ['or-clair', '#6E5836', 'bg-or-clair', "l'or quand c'est du texte"],
                    ['creme', '#F5F1E8', 'bg-creme', 'invariant : pastille logo, texte sur rouge'],
                ];
            @endphp

            <ul class="grid grid-cols-2 gap-3">
                @foreach ($couleurs as [$nom, $hex, $classe, $usage])
                    <li class="rounded border border-nuit-bord bg-nuit-haut p-3">
                        <div class="h-12 rounded {{ $classe }} {{ in_array($nom, ['nuit', 'nuit-haut']) ? 'border border-nuit-bord' : '' }}"></div>
                        <p class="mt-2 text-14 font-medium text-ivoire">{{ $nom }}</p>
                        <p class="font-mono text-12 text-ivoire-bas">{{ $hex }}</p>
                        <p class="mt-1 text-12 text-ivoire-bas">{{ $usage }}</p>
                    </li>
                @endforeach
            </ul>

            <x-alerte type="info" titre="Règle de contraste">
                L'or foncé sur nuit donne un ratio d'environ 3.5:1, insuffisant pour du texte.
                Dès qu'il s'agit de lettres, c'est or-clair.
            </x-alerte>
        </section>

        {{-- ================= 02 · Typographie ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">02</span>
                <span class="titre text-16 text-ivoire">Typographie</span>
            </h2>

            {{-- Archivo — display --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="text-12 text-ivoire-bas">Archivo variable · wdth 120 · majuscules · display</p>
                <div class="mt-3 space-y-2 overflow-hidden">
                    <p class="titre text-20 text-ivoire">Tembo <span class="font-mono text-12 text-ivoire-bas normal-case tracking-normal">20</span></p>
                    <p class="titre text-26 text-ivoire">Tembo <span class="font-mono text-12 text-ivoire-bas normal-case tracking-normal">26</span></p>
                    <p class="titre text-34 text-ivoire">Tembo <span class="font-mono text-12 text-ivoire-bas normal-case tracking-normal">34</span></p>
                    <p class="titre text-48 text-ivoire">Tembo <span class="font-mono text-12 text-ivoire-bas normal-case tracking-normal">48</span></p>
                    {{-- 72 : taille réservée au mur LED et à la révélation — spécimen court pour tenir en 320 px --}}
                    <p class="titre text-72 text-ivoire">Vote <span class="font-mono text-12 text-ivoire-bas normal-case tracking-normal">72</span></p>
                </div>
            </div>

            {{-- Instrument Sans — texte --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="text-12 text-ivoire-bas">Instrument Sans · corps, libellés, boutons</p>
                <div class="mt-3 space-y-2">
                    <p class="text-16 text-ivoire">Prenez un selfie avec votre Tembo 50 Cl et publiez-le dans la galerie de la soirée. <span class="font-mono text-12 text-ivoire-bas">16 / 400</span></p>
                    <p class="text-16 font-medium text-ivoire">Votre photo est en cours de validation. <span class="font-mono text-12 text-ivoire-bas">16 / 500</span></p>
                    <p class="text-14 text-ivoire-bas">Texte secondaire : libellés, aides, précisions. <span class="font-mono text-12">14</span></p>
                    <p class="text-12 text-ivoire-bas">Mentions légales et notes de pied de page. <span class="font-mono">12</span></p>
                </div>
            </div>

            {{-- JetBrains Mono — chiffres --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="text-12 text-ivoire-bas">JetBrains Mono 500 · chiffres : la chasse fixe empêche la largeur de sauter en animation</p>
                <div class="mt-3 flex flex-wrap items-end gap-6">
                    <div>
                        <p class="text-12 text-ivoire-bas">Photos</p>
                        <p class="font-mono text-48 text-ivoire">248</p>
                    </div>
                    <div>
                        <p class="text-12 text-ivoire-bas">Votes</p>
                        <p class="font-mono text-34 text-or-clair">127</p>
                    </div>
                    <div>
                        <p class="text-12 text-ivoire-bas">Rang</p>
                        {{-- Avatar de rang : seule exception autorisée au rounded-full --}}
                        <div class="mt-1 flex size-11 items-center justify-center rounded-full border border-or">
                            <span class="font-mono text-20 text-or-clair">1</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= 03 · Boutons ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">03</span>
                <span class="titre text-16 text-ivoire">Boutons</span>
            </h2>

            <div class="flex flex-wrap items-center gap-3">
                <x-bouton>Publier ma photo</x-bouton>
                <x-bouton variante="secondaire">Voter</x-bouton>
                <x-bouton variante="discret">Reprendre la photo</x-bouton>
                <x-bouton disabled>Envoyer</x-bouton>
            </div>

            <x-bouton class="w-full">Action pleine largeur</x-bouton>

            <p class="text-12 text-ivoire-bas">
                Zone tactile minimum 44 px. Survol : changement d'opacité, rien d'autre.
                Tabulez pour vérifier le focus clavier or-clair.
            </p>
        </section>

        {{-- ================= 04 · Champs ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">04</span>
                <span class="titre text-16 text-ivoire">Champs</span>
            </h2>

            <x-champ
                label="Prénom ou pseudo"
                name="demo-prenom"
                placeholder="Aïcha"
                aide="2 à 24 caractères. C'est le nom affiché sous votre photo."
            />

            <x-champ
                label="Champ en erreur"
                name="demo-erreur"
                placeholder="Aïcha"
                erreur="Ce prénom dépasse 24 caractères. Raccourcissez-le pour continuer."
            />

            <x-champ
                label="Champ désactivé"
                name="demo-off"
                value="Non modifiable"
                disabled
                class="opacity-40"
            />
        </section>

        {{-- ================= 05 · Alertes ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">05</span>
                <span class="titre text-16 text-ivoire">Alertes</span>
            </h2>

            <div class="space-y-3">
                <x-alerte type="info">
                    Le vote reste ouvert : vous pouvez changer de photo à tout moment.
                </x-alerte>

                <x-alerte type="succes" titre="Photo envoyée">
                    Elle apparaîtra dans la galerie d'ici quelques instants, après validation.
                </x-alerte>

                <x-alerte type="erreur" titre="Le réseau a coupé pendant l'envoi">
                    Votre photo n'est pas partie. Vérifiez votre connexion, puis appuyez de nouveau sur Envoyer.
                </x-alerte>
            </div>
        </section>

        {{-- ================= 06 · Ruban Tembo ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">06</span>
                <span class="titre text-16 text-ivoire">Ruban Tembo</span>
            </h2>

            <p class="text-14 text-ivoire-bas">
                Le bandeau ondulé du logo, repris à exactement deux endroits du produit —
                nulle part ailleurs.
            </p>

            {{-- Usage 1 : filet or sous le titre du Mode Écran --}}
            <div class="rounded border border-nuit-bord bg-nuit p-6 text-center">
                <p class="titre text-26 text-ivoire">Classement</p>
                <x-ruban class="mx-auto mt-3 max-w-48" />
                <p class="mt-2 font-mono text-12 text-ivoire-bas">1 · filet or, sous le titre du Mode Écran</p>
            </div>

            {{-- Usage 2 : bord supérieur de la barre de vote fixe (maquette statique) --}}
            <div class="overflow-hidden rounded border border-nuit-bord">
                <x-ruban variante="plein" />
                <div class="flex items-center justify-between gap-4 bg-nuit-haut px-5 py-3">
                    <div>
                        <p class="text-12 text-ivoire-bas">Mon vote</p>
                        <p class="text-16 font-medium text-ivoire">Aïcha</p>
                    </div>
                    <x-bouton variante="secondaire">Classement</x-bouton>
                </div>
            </div>
            <p class="font-mono text-12 text-ivoire-bas">2 · plein rouge, bord supérieur de la barre de vote mobile (en situation réelle : fixe en bas d'écran, sans rayon)</p>
        </section>

        {{-- ================= 07 · États ================= --}}
        <section class="space-y-4">
            <h2 class="flex items-baseline gap-3 border-b border-nuit-bord pb-2">
                <span class="font-mono text-12 text-or-clair">07</span>
                <span class="titre text-16 text-ivoire">États conçus</span>
            </h2>

            {{-- Vide --}}
            <x-etat-vide titre="Aucune photo pour l'instant">
                Les premières photos apparaîtront ici dès leur validation.
                <x-slot:action>
                    <x-bouton>Publier ma photo</x-bouton>
                </x-slot:action>
            </x-etat-vide>

            {{-- Chargement : progression réelle, jamais de spinner indéterminé --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-14 text-ivoire">Envoi de la photo…</p>
                    <p class="font-mono text-14 text-or-clair">62&nbsp;%</p>
                </div>
                <div class="mt-3 h-1 w-full bg-nuit-bord">
                    <div class="h-1 bg-rouge" style="width: 62%"></div>
                </div>
                <p class="mt-2 text-12 text-ivoire-bas">
                    Barre de progression réelle (upload.onprogress). Un spinner indéterminé sur 4G lente
                    donne l'impression que c'est planté.
                </p>
            </div>

            {{-- Reconnexion : l'interface ne se vide jamais --}}
            <div class="rounded border border-nuit-bord bg-nuit-haut p-4">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-or-clair" aria-hidden="true"></span>
                    <p class="text-14 text-ivoire-bas">Reconnexion… le dernier état connu reste affiché.</p>
                </div>
            </div>

            {{-- Apparition : animation n° 1 (240 ms), jouée au chargement de la page --}}
            <div class="animate-apparition rounded border border-nuit-bord bg-nuit-haut p-4">
                <p class="text-14 text-ivoire">Nouvelle photo dans la galerie</p>
                <p class="mt-1 text-12 text-ivoire-bas">
                    Apparition 240 ms (opacité + translation 8 px) — l'une des trois seules animations
                    du projet. Rechargez la page pour la revoir.
                </p>
            </div>
        </section>

        {{-- ================= Pied de section ================= --}}
        <p class="border-t border-nuit-bord pt-4 text-center font-mono text-12 text-ivoire-bas">
            320 px → 1920 px · rayons 4 px · aucune ombre · espacements ×4
        </p>
    </div>
</x-layouts.guest>
