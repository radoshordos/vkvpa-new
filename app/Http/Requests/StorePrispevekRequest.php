<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class StorePrispevekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'znacka' => $this->string('znacka')->trim()->upper()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'znacka' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\/]+$/'],
            'jmeno' => ['nullable', 'string', 'max:100'],
            'text' => ['required', 'string', 'min:2', 'max:2000'],
            // Pouze rastrové formáty – SVG záměrně vyloučeno (může nést JavaScript
            // a z veřejného úložiště by představovalo uložené XSS).
            'foto' => ['nullable', 'mimes:jpeg,png,gif,webp', 'max:4096'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'znacka.required' => 'Volací znak je povinný.',
            'znacka.regex' => 'Volací znak smí obsahovat pouze písmena, číslice a lomítko.',
            'text.required' => 'Text příspěvku je povinný.',
            'text.min' => 'Text příspěvku musí mít alespoň 2 znaky.',
            'foto.mimes' => 'Soubor musí být obrázek (JPEG, PNG, GIF nebo WebP).',
            'foto.max' => 'Obrázek může mít nejvýše 4 MB.',
        ];
    }
}
