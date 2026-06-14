<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\VkvpaKola;
use App\Rules\ValidMaidenhead;
use App\Rules\ValidPhone;
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
        $ownedIdRaw = $this->session()->get('owned_data_id', 0);
        $ownedId = is_numeric($ownedIdRaw) ? (int) $ownedIdRaw : 0;

        return $ownedId > 0 && $idZaznamu === $ownedId;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Editace vlastního rezervovaného řádku (EDI nahraný na poslední
            // chvíli) smí doběhnout i po uzávěrce – vlastnictví hlídá authorize().
            if ($this->integer('id_zaznamu') > 0) {
                return;
            }

            $koloId = $this->integer('kolo');
            if ($koloId > 0 && ! $this->user()?->is_admin
                && VkvpaKola::query()->find($koloId)?->prijimaHlaseni() !== true) {
                $v->errors()->add('kolo', 'Do tohoto kola nelze odeslat hlášení – nepřijímá hlášení. / Period is not accepting entries.');
            }
        });
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'znacka' => $this->string('znacka')->trim()->upper()->value(),
            'locator' => $this->string('locator')->trim()->upper()->value(),
            'jmeno' => $this->string('jmeno')->trim()->value(),
            'telefon' => $this->string('telefon')->trim()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_zaznamu' => ['nullable', 'integer'],
            'edihead_id' => ['nullable', 'integer'],
            'kolo' => ['required', 'integer', 'exists:vkvpa_kola,id'],
            'kategorie' => ['nullable', 'integer', 'exists:vkvpa_kategorie,id'],
            'znacka' => ['required', 'string', 'max:10'],
            'locator' => ['required', 'string', 'max:6', new ValidMaidenhead],
            'jmeno' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:250'],
            'telefon' => ['required', 'string', 'max:20', new ValidPhone],
            'pocet' => ['nullable', 'integer', 'min:0'],
            'bodu_za_qso' => ['nullable', 'integer', 'min:0'],
            'nasobice' => ['nullable', 'integer', 'min:0'],
            'body' => ['nullable', 'integer', 'min:0'],
            'poznamka' => ['nullable', 'string', 'max:250'],
            'soapbox' => ['nullable', 'string', 'max:250'],
            'qrp' => ['nullable', 'boolean'],
            'lp' => ['nullable', 'boolean'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'znacka.required' => 'Chybí povinná pole! (volací znak)',
            'kolo.required' => 'Chybí povinná pole! (kolo)',
            'jmeno.required' => 'Chybí povinná pole! (jméno)',
            'email.required' => 'Chybí povinná pole! (e-mail)',
            'telefon.required' => 'Chybí povinná pole! (telefon)',
            'locator.required' => 'Chybí povinná pole! (lokátor)',
        ];
    }
}
