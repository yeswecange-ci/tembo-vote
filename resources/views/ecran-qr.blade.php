{{--
    Page dédiée au QR d'accès — tablette à l'entrée ou second écran.
    Toujours affichée, quelle que soit la phase de la soirée. Le QR et le
    code se rafraîchissent seuls (rotation toutes les 20 minutes), et la
    page réutilise le composant écran : mêmes réflexes de résilience.
--}}
<x-layouts.screen title="Accès — Soirée Club Tembo">
    <div
        x-data="ecran"
        data-url-api="{{ route('api.ecran', ['cle' => $cle]) }}"
        data-initial="{{ json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        class="relative flex h-full flex-col"
    >
        {{-- Perte de connexion : un point discret, le QR affiché reste utilisable --}}
        <div x-show="horsLigne" x-cloak class="absolute left-[2.5vw] top-[3vh] z-10" aria-hidden="true">
            <span class="block size-[1.4vh] rounded-full bg-or"></span>
        </div>

        <section class="flex flex-1 flex-col items-center justify-center gap-[3.5vh] px-[8vw] text-center">
            <div class="flex size-[13vh] items-center justify-center rounded-full border border-nuit-bord bg-creme">
                <img src="{{ asset('images/logo-tembo.png') }}" alt="Tembo" class="w-[8vh]">
            </div>

            <div>
                <h1 class="titre text-[clamp(1.75rem,6vh,5.5rem)] text-ivoire">Soirée Club Tembo</h1>
                <x-ruban class="mx-auto mt-[2vh] w-[32vw]" />
            </div>

            <p class="text-[clamp(1rem,2.6vh,2rem)] text-ivoire-bas">
                Scannez pour publier votre selfie et voter — vous entrez directement.
            </p>

            <img
                :src="qr"
                src="{{ $initial['qr'] }}"
                alt="QR code d’accès à la soirée"
                class="w-[34vmin] rounded bg-creme p-[1.4vmin]"
            >

            <div>
                <p class="text-[clamp(0.875rem,2vh,1.5rem)] text-ivoire-bas">ou saisissez le code d’accès</p>
                <p class="font-mono text-[clamp(2.5rem,9vh,8rem)] leading-none text-or-clair" x-text="pin">{{ $initial['pin'] }}</p>
            </div>
        </section>

        <footer class="flex h-[6.5vh] shrink-0 items-center justify-center border-t border-nuit-bord px-[2.5vw] text-[clamp(0.75rem,1.9vh,1.5rem)] text-ivoire-bas">
            <p class="truncate">{{ config('tembo.legal.responsible_drinking') }}</p>
        </footer>
    </div>
</x-layouts.screen>
