<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ObrazekProcessor;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StorePrispevekRequest extends FormRequest
{
    /** Maximální počet fotek na jeden příspěvek. */
    public const MAX_FOTEK = 5;

    /** Maximální velikost jednoho nahraného souboru v kB (před zmenšením). */
    private const MAX_KB = 12288; // 12 MB

    public function authorize(): bool
    {
        return true;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'znacka' => $this->string('znacka')->trim()->upper()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // HEIC/HEIF zvládneme dekódovat jen s Imagickem – bez něj je do povolených
        // formátů nezařazujeme, ať uživatel dostane srozumitelnou chybu hned.
        $formaty = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'avif'];
        if (ObrazekProcessor::imagickKDispozici()) {
            $formaty[] = 'heic';
            $formaty[] = 'heif';
        }

        return [
            'znacka' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\/]+$/'],
            'jmeno' => ['nullable', 'string', 'max:100'],
            'text' => ['required', 'string', 'min:2', 'max:2000'],
            'fotky' => ['nullable', 'array', 'max:'.self::MAX_FOTEK],
            // Rastrové formáty – SVG záměrně vyloučeno (může nést JavaScript).
            // Obrázky se navíc vždy překódují, takže se neukládá původní soubor.
            'fotky.*' => ['file', 'mimes:'.implode(',', $formaty), 'max:'.self::MAX_KB],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'znacka.required' => 'Volací znak je povinný.',
            'znacka.regex' => 'Volací znak smí obsahovat pouze písmena, číslice a lomítko.',
            'text.required' => 'Text příspěvku je povinný.',
            'text.min' => 'Text příspěvku musí mít alespoň 2 znaky.',
            'fotky.max' => 'Najednou lze nahrát nejvýše '.self::MAX_FOTEK.' fotek.',
            'fotky.*.mimes' => 'Soubor musí být obrázek (JPEG, PNG, GIF, WebP, AVIF nebo HEIC).',
            'fotky.*.max' => 'Každá fotka může mít nejvýše 12 MB.',
            'fotky.*.file' => 'Nahraný soubor není platný.',
        ];
    }
}
