<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Validace přihlašovacího formuláře administrace.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'heslo' => ['required', 'string'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'username.required' => 'Zadejte přihlašovací jméno.',
            'heslo.required' => 'Zadejte heslo.',
        ];
    }
}
