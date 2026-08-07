{{--
    État vide conçu : un écran vide est une invitation à agir, pas un trou.
    Toujours un titre, une explication, et si possible une action.
--}}
@props([
    'titre',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded border border-nuit-bord bg-nuit-haut px-6 py-10 text-center']) }}>
    <p class="text-16 font-medium text-ivoire">{{ $titre }}</p>

    <p class="mt-2 text-14 text-ivoire-bas">{{ $slot }}</p>

    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
