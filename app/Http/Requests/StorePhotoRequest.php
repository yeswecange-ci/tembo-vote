<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'accès est déjà garanti par le middleware guest.session
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('display_name')) {
            // Contenu utilisateur : balises retirées, une seule ligne
            $this->merge([
                'display_name' => Str::of((string) $this->input('display_name'))->stripTags()->squish()->value(),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // HEIC volontairement absent : GD ne le décode pas. La compression
            // côté client produit toujours du JPEG ; ce cas ne concerne que le
            // repli sans canvas, refusé avec une consigne claire.
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:'.(int) config('tembo.upload_max_kb')],
            'display_name' => ['required', 'string', 'min:2', 'max:24'],
            'consent_event' => ['accepted'],
            'consent_reuse' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Ajoutez une photo avant d’envoyer.',
            'photo.file' => 'Ajoutez une photo avant d’envoyer.',
            'photo.image' => 'Ce format n’est pas pris en charge. Reprenez la photo avec l’appareil photo du téléphone.',
            'photo.mimes' => 'Ce format n’est pas pris en charge. Reprenez la photo avec l’appareil photo du téléphone.',
            'photo.max' => 'Cette photo dépasse '.round((int) config('tembo.upload_max_kb') / 1024).' Mo. Reprenez-la avec l’appareil photo : elle sera compressée automatiquement.',
            'display_name.required' => 'Indiquez le prénom ou le pseudo à afficher sous votre photo.',
            'display_name.min' => 'Le prénom doit faire entre 2 et 24 caractères.',
            'display_name.max' => 'Le prénom doit faire entre 2 et 24 caractères.',
            'consent_event.accepted' => 'Cochez la case de consentement pour que votre photo soit affichée pendant la soirée.',
        ];
    }
}
