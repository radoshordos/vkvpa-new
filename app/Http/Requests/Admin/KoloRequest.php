<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\VkvpaKola;
use Carbon\CarbonImmutable;
use Closure;
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
            // Kolo se koná třetí neděli v měsíci → jeden termín = nejvýš jedno
            // kolo (DB to jistí unikátním indexem, tady chceme hezkou hlášku).
            'datum_konani' => [
                'required', 'date',
                Rule::unique('vkvpa_kola', 'datum_konani')->ignore($this->route('kolo')),
                $this->startPosunRule(),
            ],
            'datum_uzaverky' => ['required', 'date'],
            'poznamka' => ['nullable', 'string', 'max:250'],
            'vyhodnoceno' => ['nullable', 'date'],
        ];
    }

    /**
     * Při úpravě kola smí být start závodu posunut nejvýše o 7 dní oproti
     * původnímu termínu. Při vytváření (žádné původní kolo) se neuplatní.
     */
    private function startPosunRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $kolo = $this->route('kolo');
            if (! $kolo instanceof VkvpaKola || ! is_string($value)) {
                return;
            }

            $puvodni = CarbonImmutable::parse($kolo->datum_konani);
            $novy = CarbonImmutable::parse($value);

            if ($puvodni->diffInDays($novy, true) > 7) {
                $fail('Start závodu lze posunout nejvýše o 7 dní oproti původnímu termínu.');
            }
        };
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
            // poznamka je v DB NOT NULL – string() vrátí prázdný řetězec místo null.
            'poznamka' => $this->string('poznamka')->value(),
            // Prázdné pole = nevyhodnoceno (NULL); vyplněné = terminální stav.
            'vyhodnoceno' => $this->filled('vyhodnoceno') ? $this->string('vyhodnoceno')->value() : null,
        ];
    }
}
