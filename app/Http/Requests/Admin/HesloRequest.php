<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Změna vlastního hesla administrátora.
 *
 * Vyžaduje potvrzení současného hesla (current_password) a dvojí zadání
 * nového hesla (confirmed → pole heslo_confirmation).
 */
class HesloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'soucasne_heslo' => ['required', 'string', 'current_password'],
            'heslo' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'soucasne_heslo' => 'současné heslo',
            'heslo' => 'nové heslo',
        ];
    }
}
