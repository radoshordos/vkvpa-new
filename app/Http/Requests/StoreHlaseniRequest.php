<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\VkvpaKola;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * Validace podání hlášení.
 *
 * Povinné: značka, kolo, e-mail, lokátor.
 */
class StoreHlaseniRequest extends FormRequest
{
    public function authorize(): bool
    {
        $idZaznamu = $this->integer('id_zaznamu');
        if ($idZaznamu === 0) {
            return true;
        }
        if ($this->user()?->is_admin) {
            return true;
        }
        $ownedId = (int) $this->session()->get('owned_data_id', 0);

        return $ownedId > 0 && $idZaznamu === $ownedId;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $koloId = $this->integer('kolo');
            if ($koloId > 0 && ! $this->user()?->is_admin && ! VkvpaKola::jeAktivni($koloId)) {
                $v->errors()->add('kolo', 'Do tohoto kola nelze odeslat hlášení – není aktivní. / Period is not active.');
            }
        });
    }

    #[Override]
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
            'znacka' => ['required', 'string', 'max:10'],
            'locator' => ['required', 'string', 'max:6'],
            'email' => ['required', 'email', 'max:250'],
            'pocet' => ['nullable', 'integer', 'min:0'],
            'bodu_za_qso' => ['nullable', 'integer', 'min:0'],
            'nasobice' => ['nullable', 'integer', 'min:0'],
            'body' => ['nullable', 'integer', 'min:0'],
            'jmeno' => ['nullable', 'string', 'max:60'],
            'telefon' => ['nullable', 'string', 'max:20'],
            'poznamka' => ['nullable', 'string'],
            'soapbox' => ['nullable', 'string'],
            'qrp' => ['nullable', 'boolean'],
        ];
    }

    #[Override]
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
