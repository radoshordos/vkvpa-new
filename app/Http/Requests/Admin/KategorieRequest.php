<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Validace vytvoření i úpravy kategorie (pravidla jsou shodná).
 * Přístup řeší middleware „admin" na routě.
 */
class KategorieRequest extends FormRequest
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
            'nazev' => ['required', 'string', 'max:50'],
            // CategoryResolver páruje sekci z EDI hlavičky přes zkratku –
            // duplicitní zkratka by párování učinila nedeterministickým
            // (DB to jistí unikátním indexem, tady chceme hezkou hlášku).
            'zkratka' => [
                'required', 'string', 'max:20',
                Rule::unique('vkvpa_kategorie', 'zkratka')->ignore($this->route('kategorie')),
            ],
            // 0 = tuzemská; nenulové = id odpovídající tuzemské kategorie
            'dxid' => ['required', 'integer', 'min:0'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'nazev.required' => 'Název kategorie je povinný.',
            'zkratka.required' => 'Zkratka kategorie je povinná.',
            'zkratka.unique' => 'Kategorie s touto zkratkou už existuje.',
            'dxid.required' => 'Pole dxid je povinné.',
            'dxid.integer' => 'Pole dxid musí být celé číslo.',
            'dxid.min' => 'Pole dxid nesmí být záporné.',
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
            'zkratka' => $this->string('zkratka')->value(),
            'dxid' => $this->integer('dxid'),
        ];
    }
}
