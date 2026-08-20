{{--
    Mode Écran — mur LED 3 × 4 m, résolution non confirmée.
    Toute la mise en page est en vw / vh / clamp, aucune valeur fixe en pixels.
    La phase initiale est rendue côté serveur : même si le JavaScript mourait,
    l'écran afficherait un état figé cohérent, jamais une page blanche.
--}}
<x-layouts.screen title="Écran — Soirée Castel Beer Afterwork">
    <div
        x-data="ecran"
        data-url-api="{{ route('api.ecran', ['cle' => $cle]) }}"
        data-initial="{{ json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        class="relative flex h-full flex-col"
    >
        {{--
            Accès en permanence dans le coin : le QR est le seul chemin
            d'entrée, il doit rester scannable depuis le fond de la salle.
        --}}
        <div
            class="absolute right-[2vw] top-[2.5vh] z-10 flex items-center gap-[1.2vw]"
            x-show="!['setup', 'reveal', 'closed'].includes(phase)"
            @if (in_array($initial['phase'], ['setup', 'reveal', 'closed'])) style="display: none" @endif
        >
            <p class="text-right text-[clamp(0.7rem,1.5vh,1.2rem)] uppercase text-ivoire-bas">
                Scannez<br>pour entrer
            </p>
            <img
                :src="qr"
                src="{{ $initial['qr'] }}"
                alt="QR code d’accès à la soirée"
                class="w-[12vmin] shrink-0 rounded bg-creme p-[0.6vmin]"
            >
        </div>

        {{-- Perte de connexion : un point discret, jamais un message d'erreur --}}
        <div x-show="horsLigne" x-cloak class="absolute left-[2.5vw] top-[3vh] z-10" aria-hidden="true">
            <span class="block size-[1.4vh] rounded-full bg-or"></span>
        </div>

        {{-- ===== Attente (setup) : logo + consigne + QR géant ===== --}}
        <section
            x-show="phase === 'setup'"
            @if ($initial['phase'] !== 'setup') style="display: none" @endif
            class="flex flex-1 flex-col items-center justify-center gap-[4vh] px-[8vw] text-center"
        >
            <div class="flex size-[16vh] items-center justify-center rounded-full border border-nuit-bord bg-creme">
                <img src="{{ asset('images/logo-castel.png') }}" alt="Castel Beer" class="w-[11.5vh]">
            </div>
            <div>
                <h1 class="titre text-[clamp(2rem,7vh,6.5rem)] text-ivoire">Soirée Castel Beer Afterwork</h1>
                <x-ruban class="mx-auto mt-[2vh] w-[32vw]" />
            </div>
            <p class="text-[clamp(1rem,2.8vh,2.25rem)] text-ivoire-bas">
                Scannez le QR code : vous entrez directement, sans rien saisir.
            </p>
            {{-- QR d'accès, renouvelé toutes les 5 minutes par le polling --}}
            <img
                :src="qr"
                src="{{ $initial['qr'] }}"
                alt="QR code d’accès à la soirée"
                class="w-[34vmin] rounded bg-creme p-[1.2vmin]"
            >
        </section>

        {{-- ===== Classement live (open / vote_only / frozen) ===== --}}
        <section
            x-show="['open', 'vote_only', 'frozen'].includes(phase)"
            @if (! in_array($initial['phase'], ['open', 'vote_only', 'frozen'])) style="display: none" @endif
            class="flex min-h-0 flex-1 flex-col"
        >
            {{--
                Marge latérale de 26vw (le titre ne passe jamais sous le QR) et
                échelle en vmin : la même mise en page tient en paysage comme en
                portrait 3:4, la résolution du mur n'étant pas confirmée.
            --}}
            <header class="px-[26vw] pt-[3vh] text-center">
                <h1 class="titre text-[clamp(1.5rem,5vmin,4rem)] text-ivoire">La photo de la soirée</h1>
                {{-- Ruban or : premier des deux emplacements autorisés du motif --}}
                <x-ruban class="mx-auto mt-[1.5vh] w-[28vw]" />
                <p x-show="phase === 'frozen'" x-cloak class="titre mt-[1.5vh] text-[clamp(1rem,3.5vmin,2.5rem)] text-rouge">
                    Votes clos
                </p>
            </header>

            {{-- Top 5 : rang or monospace surdimensionné, vignette, prénom, compteur animé --}}
            <ol class="flex min-h-0 flex-1 flex-col justify-center gap-[1.8vmin] px-[6vw] py-[2vh]" x-show="top.length">
                <template x-for="(entree, indice) in top" :key="entree.id">
                    <li class="flex items-center gap-[2.5vw] border-b border-nuit-bord pb-[1.8vmin] last:border-b-0 last:pb-0">
                        <span
                            class="w-[8vw] shrink-0 text-right font-mono text-[clamp(1.75rem,8vmin,6rem)] leading-none text-or-clair"
                            x-text="indice + 1"
                        ></span>
                        <img
                            :src="entree.vignette"
                            :alt="'Photo de ' + entree.nom"
                            class="size-[10.5vmin] shrink-0 rounded bg-nuit-haut object-cover"
                        >
                        <span
                            class="min-w-0 flex-1 truncate text-[clamp(1.25rem,4vmin,3.25rem)] font-medium text-ivoire"
                            x-text="entree.nom"
                        ></span>
                        <span class="shrink-0 text-right">
                            <span class="block font-mono text-[clamp(1.5rem,5.5vmin,4.5rem)] leading-none text-ivoire" x-text="entree.votesAffiches"></span>
                            <span class="block text-[clamp(0.7rem,2vmin,1.25rem)] text-ivoire-bas">votes</span>
                        </span>
                    </li>
                </template>
            </ol>

            {{-- Classement encore vide : une invitation, pas un trou --}}
            <div x-show="!top.length" class="flex flex-1 flex-col items-center justify-center gap-[2vh] text-center">
                <p class="text-[clamp(1.25rem,3.5vh,3rem)] text-ivoire">Les premières photos arrivent…</p>
                <p class="text-[clamp(1rem,2.4vh,2rem)] text-ivoire-bas">
                    Scannez le QR code, publiez votre selfie et lancez le classement.
                </p>
            </div>
        </section>

        {{-- ===== Révélation du gagnant (fondu 1200 ms, aucune autre animation) ===== --}}
        <section
            x-show="phase === 'reveal'"
            @if ($initial['phase'] !== 'reveal') style="display: none" @endif
            class="animate-revelation flex min-h-0 flex-1 flex-col items-center justify-center gap-[3vh] px-[8vw] py-[4vh] text-center"
        >
            <template x-if="gagnant()">
                <div class="flex w-full flex-col items-center gap-[3vh]">
                    <p class="titre text-[clamp(1.25rem,3.5vh,3rem)] text-or-clair">La photo de la soirée</p>
                    <img
                        :src="gagnant().plein"
                        :alt="'Photo gagnante de ' + gagnant().nom"
                        class="max-h-[56vh] max-w-[82vw] rounded object-contain"
                    >
                    <div>
                        <p class="titre text-[clamp(2rem,7vh,6rem)] leading-tight text-ivoire" x-text="gagnant().nom"></p>
                        <x-ruban class="mx-auto mt-[1.5vh] w-[22vw]" />
                    </div>
                </div>
            </template>
        </section>

        {{-- ===== Remerciement (closed) ===== --}}
        <section
            x-show="phase === 'closed'"
            @if ($initial['phase'] !== 'closed') style="display: none" @endif
            class="flex flex-1 flex-col items-center justify-center gap-[4vh] text-center"
        >
            <div class="flex size-[16vh] items-center justify-center rounded-full border border-nuit-bord bg-creme">
                <img src="{{ asset('images/logo-castel.png') }}" alt="Castel Beer" class="w-[11.5vh]">
            </div>
            <div>
                <p class="titre text-[clamp(2.5rem,9vh,8rem)] text-ivoire">Merci</p>
                <x-ruban class="mx-auto mt-[2vh] w-[24vw]" />
            </div>
            <p class="text-[clamp(1rem,2.8vh,2.25rem)] text-ivoire-bas">Soirée Castel Beer Afterwork · 21 août 2026</p>
        </section>

        {{-- Bandeau du bas : stats + consommation responsable, sur tous les écrans --}}
        <footer class="flex h-[6.5vh] shrink-0 items-center justify-between gap-[3vw] border-t border-nuit-bord px-[2.5vw] text-[clamp(0.75rem,1.9vh,1.5rem)] text-ivoire-bas">
            <p
                class="shrink-0"
                x-show="['open', 'vote_only', 'frozen'].includes(phase)"
                @if (! in_array($initial['phase'], ['open', 'vote_only', 'frozen'])) style="display: none" @endif
            >
                <span class="font-mono text-ivoire" x-text="stats.photos">{{ $initial['stats']['photos'] }}</span> photos ·
                <span class="font-mono text-ivoire" x-text="stats.votes">{{ $initial['stats']['votes'] }}</span> votes
            </p>
            <p class="min-w-0 flex-1 truncate text-right">{{ config('tembo.legal.responsible_drinking') }}</p>
        </footer>
    </div>
</x-layouts.screen>
