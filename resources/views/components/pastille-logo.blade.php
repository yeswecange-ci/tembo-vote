{{--
    Pastille de 56 px : le support du logo (rouge sur clair) — toujours crème,
    quel que soit le thème, avec un filet pour se détacher du fond clair.
--}}
<div {{ $attributes->merge(['class' => 'flex size-14 shrink-0 items-center justify-center rounded-full border border-nuit-bord bg-creme']) }}>
    <img src="{{ asset('images/logo-tembo.png') }}" alt="Tembo" class="w-9">
</div>
