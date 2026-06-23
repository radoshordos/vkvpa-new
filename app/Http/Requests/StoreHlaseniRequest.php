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
 * Povinné: značka, kolo, lokátor, jméno. U podání s EDI deníkem je navíc
 * povinný alespoň jeden kontakt – telefon nebo e-mail (potvrzení o přijetí
 * se posílá na e-mail, telefon je záložní kontakt). U ručního podání zůstává
 * povinný telefon.
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
            // U podání s EDI deníkem musí být vyplněn alespoň jeden kontakt
            // (telefon nebo e-mail) – platí i pro dokončení vlastního
            // rezervovaného řádku, proto se kontroluje před návratem níže.
            if ($this->integer('edihead_id') > 0) {
                $email = $this->string('email')->trim()->value();
                $telefon = $this->string('telefon')->trim()->value();
                if ($email === '' && $telefon === '') {
                    $v->errors()->add('telefon', 'U podání s EDI deníkem vyplňte alespoň jeden kontakt – telefon, nebo e-mail.');
                }
            }

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
            // Prázdný telefon necháváme jako null, aby ho pravidlo `nullable`
            // přeskočilo (jinak by ValidPhone spadl na prázdném řetězci).
            'telefon' => ($t = $this->string('telefon')->trim()->value()) === '' ? null : $t,
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
            // U podání s EDI deníkem stačí jeden kontakt (telefon NEBO e-mail) –
            // „alespoň jeden" hlídá withValidator(). Jednotlivě tedy nejsou
            // povinné. U ručního podání zůstává povinný telefon.
            'email' => ['nullable', 'email', 'max:250'],
            'telefon' => [$this->integer('edihead_id') > 0 ? 'nullable' : 'required', 'string', 'max:20', new ValidPhone],
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
            'telefon.required' => 'Chybí povinná pole! (telefon)',
            'locator.required' => 'Chybí povinná pole! (lokátor)',
        ];
    }
}
