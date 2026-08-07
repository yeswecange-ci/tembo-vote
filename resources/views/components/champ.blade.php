{{--
    Champ de formulaire : libellé, saisie, message d'erreur ou aide.
    L'erreur dit ce qui s'est passé et quoi faire — jamais de message générique.
--}}
@props([
    'label',
    'name',
    'type' => 'text',
    'erreur' => null,
    'aide' => null,
])

<div class="w-full">
    <label for="{{ $name }}" class="mb-2 block text-14 font-medium text-ivoire-bas">{{ $label }}</label>

    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($erreur) aria-invalid="true" aria-describedby="{{ $name }}-erreur" @endif
        {{ $attributes->merge([
            'class' => 'block min-h-11 w-full rounded border bg-nuit-haut px-4 text-16 text-ivoire placeholder:text-ivoire-bas focus:border-or-clair '
                .($erreur ? 'border-rouge' : 'border-nuit-bord'),
        ]) }}
    >

    @if ($erreur)
        <p id="{{ $name }}-erreur" class="mt-2 text-14 text-rouge">{{ $erreur }}</p>
    @elseif ($aide)
        <p class="mt-2 text-12 text-ivoire-bas">{{ $aide }}</p>
    @endif
</div>
