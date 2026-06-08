<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\VkvpaSettings;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Validace nahraného EDI deníku při běžném podání hlášení.
 */
class StoreEdiRequest extends FormRequest
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
            'upload' => ['required', 'file', 'max:'.VkvpaSettings::ediMaxSizeKb(), 'extensions:edi,txt'],
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
