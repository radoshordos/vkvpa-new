<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validace podání hlášení (sladěno s edit_hlaseni.php v4.1.3).
 *
 * Povinné: značka, kolo, e-mail, lokátor (jako v reálném POST handleru).
 * Žádný striktní součinový check – ta verze ho nemá. Žádné SQL injection
 * (na rozdíl od legacy mysqli_real_escape_string + interpolace).
 */
class StoreHlaseniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'znacka' => $this->string('znacka')->trim()->upper()->value(),
            'locator' => $this->string('locator')->trim()->upper()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_zaznamu' => ['nullable', 'integer'],
            'EDIID' => ['nullable', 'integer'],
            'EDI' => ['nullable', 'boolean'],
            'kolo' => ['required', 'integer', 'exists:vkvpa_kola,id'],
            'kategorie' => ['nullable', 'integer', 'exists:vkvpa_kategorie,id'],
            'znacka' => ['required', 'string', 'max:20'],
            'locator' => ['required', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:250'],
            'pocet' => ['nullable', 'integer', 'min:0'],
            'bodu_za_qso' => ['nullable', 'integer', 'min:0'],
            'nasobice' => ['nullable', 'integer', 'min:0'],
            'body' => ['nullable', 'integer', 'min:0'],
            'jmeno' => ['nullable', 'string', 'max:60'],
            'telefon' => ['nullable', 'string', 'max:30'],
            'poznamka' => ['nullable', 'string'],
            'soapbox' => ['nullable', 'string'],
            'qrp' => ['nullable', 'boolean'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'znacka.required' => 'Chybí povinná pole! (volací znak)',
            'kolo.required' => 'Chybí povinná pole! (kolo)',
            'email.required' => 'Chybí povinná pole! (kontakt / e-mail)',
            'locator.required' => 'Chybí povinná pole! (lokátor)',
        ];
    }
}
