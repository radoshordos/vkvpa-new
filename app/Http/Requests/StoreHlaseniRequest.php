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
 * Povinné: značka, kolo, lokátor, jméno a alespoň jeden kontakt – telefon
 * nebo e-mail. Pravidlo „alespoň jeden kontakt" platí jednotně pro všechna
 * podání (s EDI deníkem i ruční); potvrzení o přijetí se posílá na e-mail,
 * telefon je záložní kontakt.
 */
class StoreHlaseniRequest extends FormRequest
{
    /** Chybová hláška, když není vyplněn ani telefon, ani e-mail. */
    public const string CHYBI_KONTAKT = 'Vyplňte prosím alespoň jeden kontakt – telefon, nebo e-mail. Pokud nechcete žádný kontakt uvést, zadejte neplatné telefonní číslo +420 999 999999. Děkujeme.';

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
            // Jednotně pro všechna podání (EDI i ruční) musí být vyplněn
            // alespoň jeden kontakt – telefon nebo e-mail. Kontroluje se před
            // návratem níže, aby platilo i pro dokončení vlastního
            // rezervovaného řádku (EDI nahraný na poslední chvíli).
            $email = $this->string('email')->trim()->value();
            $telefon = $this->string('telefon')->trim()->value();
            if ($email === '' && $telefon === '') {
                $v->errors()->add('telefon', self::CHYBI_KONTAKT);
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
            'kategorie' => ['nullable', 'integer', 'exists:edi_category,id'],
            'znacka' => ['required', 'string', 'max:10'],
            'locator' => ['required', 'string', 'max:6', new ValidMaidenhead],
            'jmeno' => ['required', 'string', 'max:60'],
            // Kontakt: jednotlivě nepovinný (telefon i e-mail), formát se ověří
            // jen u vyplněného. Podmínku „alespoň jeden" hlídá withValidator().
            'email' => ['nullable', 'email', 'max:250'],
            'telefon' => ['nullable', 'string', 'max:20', new ValidPhone],
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
            'locator.required' => 'Chybí povinná pole! (lokátor)',
        ];
    }
}
