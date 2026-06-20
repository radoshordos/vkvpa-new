<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Validace nahraného EDI deníku pro debug rozpad bodování (jen náhled).
 * Menší limit než běžný upload – jde o dočasný náhled.
 * Přístup řeší middleware „admin" na routě.
 */
class EdiDebugUploadRequest extends FormRequest
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
            'upload' => ['required', 'file', 'max:500', 'extensions:edi,txt'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'upload.required' => 'Vyberte EDI soubor.',
            'upload.extensions' => 'Soubor musí mít příponu .edi nebo .txt.',
            'upload.max' => 'Soubor je příliš velký.',
        ];
    }
}
