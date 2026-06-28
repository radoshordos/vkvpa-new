<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\EdiBand;
use App\Models\EdiCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Validace vytvoření i úpravy kategorie `edi_category` (pravidla jsou shodná).
 * Přístup řeší middleware „admin" na routě.
 *
 * Formulář posílá pásmo jako název (`band`, např. '144 MHz') z číselníku
 * `edi_bands`; {@see self::toModel()} ho přeloží na `edi_category.band_id`
 * (textový sloupec už neexistuje).
 */
class KategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Povolené názvy pásem z číselníku `edi_bands` (zdroj pravdy).
     *
     * @return array<int, string>
     */
    public static function bands(): array
    {
        return EdiBand::query()->orderBy('id')->get()
            ->map(static fn (EdiBand $b): string => $b->name)
            ->values()
            ->all();
    }

    /** id pásma podle zaslaného názvu (`band`), nebo null když je neznámý. */
    private function bandId(): ?int
    {
        $id = EdiBand::query()
            ->where('name', $this->string('band')->value())
            ->value('id');

        return is_int($id) ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kategorie = $this->route('kategorie');
        $ignoreId = $kategorie instanceof EdiCategory ? $kategorie->id : null;

        return [
            // Volitelně lze při zakládání zadat konkrétní ID (jinak se přidělí
            // automaticky). Při úpravě se ID nemění – pole se neposílá.
            'id' => ['nullable', 'integer', 'min:1', Rule::unique('edi_category', 'id')],
            'name' => ['required', 'string', 'max:50'],
            'band' => ['required', Rule::in(self::bands())],
            'section' => ['required', Rule::in(['SO', 'MO'])],
            // Přirozený klíč band_id+section+variant je unikátní (jedna kombinace
            // = jedna kategorie); kontrolujeme přes variant s where na band_id+section.
            'variant' => [
                'required', Rule::in(['domestic', 'dx']),
                Rule::unique('edi_category', 'variant')
                    ->where('band_id', $this->bandId())
                    ->where('section', $this->string('section')->value())
                    ->ignore($ignoreId),
            ],
            // NULL = tato kategorie je tuzemská; jinak id tuzemského protějšku DX řádku
            'dxid' => ['nullable', 'integer', Rule::exists('edi_category', 'id')],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Název kategorie je povinný.',
            'band.required' => 'Pásmo je povinné.',
            'band.in' => 'Neplatné pásmo.',
            'section.required' => 'Sekce je povinná.',
            'section.in' => 'Sekce musí být SO nebo MO.',
            'variant.required' => 'Varianta je povinná.',
            'variant.in' => 'Varianta musí být domestic nebo dx.',
            'variant.unique' => 'Kategorie s touto kombinací pásmo + sekce + varianta už existuje.',
            'id.integer' => 'ID musí být celé číslo.',
            'id.min' => 'ID musí být kladné číslo.',
            'id.unique' => 'Kategorie s tímto ID už existuje.',
            'dxid.integer' => 'Pole dxid musí být celé číslo.',
            'dxid.exists' => 'dxid musí odkazovat na existující kategorii.',
        ];
    }

    /**
     * Normalizovaná data pro zápis do modelu.
     *
     * @return array<string, mixed>
     */
    public function toModel(): array
    {
        $data = [
            'name' => $this->string('name')->value(),
            'band_id' => $this->bandId(),
            'section' => $this->string('section')->value(),
            'variant' => $this->string('variant')->value(),
            'dxid' => $this->filled('dxid') ? $this->integer('dxid') : null,
        ];

        // Ruční ID se uplatní jen při zakládání (na úpravě se pole neposílá).
        if ($this->filled('id')) {
            $data['id'] = $this->integer('id');
        }

        return $data;
    }
}
