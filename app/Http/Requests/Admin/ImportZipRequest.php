<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\VkvpaSettings;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Validace ZIP archivu pro hromadný import EDI deníků.
 * Přístup řeší middleware „admin" na routě.
 */
class ImportZipRequest extends FormRequest
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
            'zip' => ['required', 'file', 'max:'.VkvpaSettings::importMaxSizeKb(), 'mimes:zip'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'zip.required' => 'Vyberte ZIP archiv k importu.',
            'zip.mimes' => 'Soubor musí být ZIP archiv.',
            'zip.max' => 'ZIP archiv je příliš velký.',
        ];
    }
}
