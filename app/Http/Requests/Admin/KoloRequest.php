<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\EdiRound;
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
            'name' => ['required', 'string', 'max:250'],
            // Kolo se koná třetí neděli v měsíci → jeden termín = nejvýš jedno
            // kolo (DB to jistí unikátním indexem, tady chceme hezkou hlášku).
            'starts_at' => [
                'required', 'date',
                Rule::unique('edi_rounds', 'starts_at')->ignore($this->route('kolo')),
                $this->startPosunRule(),
            ],
            'closes_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:250'],
            'evaluated_at' => ['nullable', 'date'],
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
            if (! $kolo instanceof EdiRound || ! is_string($value)) {
                return;
            }

            $puvodni = CarbonImmutable::parse($kolo->starts_at);
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
            'starts_at.required' => 'Datum konání je povinné.',
            'starts_at.date' => 'Datum konání není platné datum.',
            'starts_at.unique' => 'Pro toto datum už kolo existuje.',
            'closes_at.required' => 'Datum uzávěrky je povinné.',
            'closes_at.date' => 'Datum uzávěrky není platné datum.',
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
            'name' => $this->string('name')->value(),
            'starts_at' => $this->string('starts_at')->value(),
            'closes_at' => $this->string('closes_at')->value(),
            // note je v DB NOT NULL – string() vrátí prázdný řetězec místo null.
            'note' => $this->string('note')->value(),
            // Prázdné pole = nevyhodnoceno (NULL); vyplněné = terminální stav.
            'evaluated_at' => $this->filled('evaluated_at') ? $this->string('evaluated_at')->value() : null,
        ];
    }
}
