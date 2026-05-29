<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\VkvpaData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validace podání hlášení (Fáze 6) – nahrazuje extract($_POST) + preg_match
 * + ruční kontroly z edit_hlaseni.php. Žádná interpolace do SQL.
 *
 * Pravidla pro běžného účastníka jsou přísná; admin („Beda") má úlevy,
 * shodně s legací (řada kontrol byla obalena `if prihlasen != Beda`).
 */
class StoreHlaseniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function isAdmin(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * Značku a lokátor převést na velká písmena (legacy strtoupper).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'znacka' => strtoupper((string) $this->input('znacka')),
            'lokator' => strtoupper((string) $this->input('lokator')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isAdmin() ? 'nullable' : 'required';

        return [
            'kolo' => ['required', 'integer', 'exists:vkvpa_kola,id'],
            'kategorie' => ['required', 'integer', 'exists:vkvpa_kategorie,id'],
            'znacka' => [$required, 'string', 'max:10', 'regex:/^[A-Z0-9]+(\/[A-Z]{1,3})*$/'],
            'lokator' => [$required, 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z]{2}$/'],
            'pocet' => [$required, 'integer', 'min:0'],
            'bodu_za_qso' => [$required, 'integer', 'min:0'],
            'nasobice' => [$required, 'integer', 'min:0'],
            'body' => [$required, 'integer', 'min:0'],
            'jmeno' => ['nullable', 'string', 'max:60'],
            // E-mail nebo telefon je povinný (jen pro běžného účastníka).
            'mail' => [$this->isAdmin() ? 'nullable' : 'required_without:telefon', 'nullable', 'email', 'max:250'],
            'telefon' => [$this->isAdmin() ? 'nullable' : 'required_without:mail', 'nullable', 'string', 'max:20'],
            'poznamka' => ['nullable', 'string'],
            'soapbox' => ['nullable', 'string'],
            'qrp' => ['nullable', 'boolean'],
            'EDI' => ['nullable', 'boolean'],
            'EDIID' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->isAdmin()) {
                return; // admin má úlevy (jako v legacy)
            }

            $pocet = (int) $this->input('pocet');
            $boduZaQso = (int) $this->input('bodu_za_qso');
            $nasobice = (int) $this->input('nasobice');
            $body = (int) $this->input('body');

            if ($boduZaQso * $nasobice !== $body) {
                $v->errors()->add('body', "Chybně zadaný počet bodů: {$boduZaQso} × {$nasobice} = " . ($boduZaQso * $nasobice) . ' bodů.');
            }
            if ($body < $pocet) {
                $v->errors()->add('body', 'Celkový počet bodů je nižší než počet QSO.');
            }
            if ($boduZaQso < $pocet) {
                $v->errors()->add('bodu_za_qso', 'Počet bodů za QSO je nižší než počet QSO.');
            }
            if ($nasobice > $pocet + 1) {
                $v->errors()->add('nasobice', 'Počet násobičů neodpovídá počtu QSO.');
            }

            // Duplicita: značka + kolo + kategorie už existuje.
            $exists = VkvpaData::query()
                ->where('znacka', $this->input('znacka'))
                ->where('id_kola', (int) $this->input('kolo'))
                ->where('id_kategorie', (int) $this->input('kategorie'))
                ->exists();

            if ($exists) {
                $v->errors()->add('znacka', 'Hlášení této stanice pro dané kolo a kategorii již bylo podáno. Pro opravu kontaktujte vyhodnocovatele.');
            }
        });
    }
}
