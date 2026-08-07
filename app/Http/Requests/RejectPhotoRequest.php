<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route protégée par le middleware auth (modérateurs uniquement)
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(config('tembo.reject_reasons'))],
            'verrou' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Choisissez un motif de refus.',
            'reason.in' => 'Choisissez un motif dans la liste.',
            'verrou.required' => 'Rechargez la page et réessayez.',
        ];
    }
}
