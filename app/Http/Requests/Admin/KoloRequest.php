<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Validace vytvoření i úpravy kola závodu.
 * Přístup řeší middleware „admin" na routě.
 */
class KoloRequest extends FormRequest
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
            'nazev' => ['required', 'string', 'max:250'],
            // Kolo se koná třetí neděli v měsíci → jeden den = nejvýš jedno kolo
            // (DB to jistí unikátním indexem, tady chceme hezkou hlášku).
            'datum_konani' => [
                'required', 'date',
                Rule::unique('vkvpa_kola', 'datum_konani')->ignore($this->route('kolo')),
            ],
            'datum_uzaverky' => ['required', 'date'],
            'aktivni' => ['boolean'],
            'poznamka' => ['nullable', 'string', 'max:250'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'nazev.required' => 'Název kola je povinný.',
            'datum_konani.required' => 'Datum konání je povinné.',
            'datum_konani.date' => 'Datum konání není platné datum.',
            'datum_konani.unique' => 'Pro toto datum už kolo existuje.',
            'datum_uzaverky.required' => 'Datum uzávěrky je povinné.',
            'datum_uzaverky.date' => 'Datum uzávěrky není platné datum.',
        ];
    }

    /**
     * Normalizovaná data pro zápis do modelu.
     *
     * @return array<string, mixed>
     */
    public function toModel(): array
    {
        return [
            'nazev' => $this->string('nazev')->value(),
            'datum_konani' => $this->string('datum_konani')->value(),
            'datum_uzaverky' => $this->string('datum_uzaverky')->value(),
            'aktivni' => $this->boolean('aktivni'),
            // poznamka je v DB NOT NULL – string() vrátí prázdný řetězec místo null.
            'poznamka' => $this->string('poznamka')->value(),
        ];
    }
}
