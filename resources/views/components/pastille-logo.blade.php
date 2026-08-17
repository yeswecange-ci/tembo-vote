{{--
    Pastille de 56 px : le support du logo Castel Beer — toujours crème,
    quel que soit le thème, avec un filet pour se détacher du fond clair.
--}}
<div {{ $attributes->merge(['class' => 'flex size-14 shrink-0 items-center justify-center rounded-full border border-nuit-bord bg-creme']) }}>
    {{-- 40/56 : au-delà de ~72 % du disque, le mot « Castel » dépasse du cercle
         crème (il est plus large que l'écusson) ; en dessous, il devient illisible. --}}
    <img src="{{ asset('images/logo-castel.png') }}" alt="Castel Beer" class="w-10">
</div>
