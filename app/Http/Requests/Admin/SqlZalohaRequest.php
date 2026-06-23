<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\VkvpaSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

/**
 * Validace výběru tabulek pro SQL zálohu.
 *
 * `tables` smí obsahovat jen názvy z allowlistu (`vkvpa.db_backup_table_groups`),
 * takže ani podvržený POST neumožní dump libovolné (např. `users`) tabulky.
 * Přístup řeší middleware „admin" na routě.
 */
class SqlZalohaRequest extends FormRequest
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
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['string', Rule::in(VkvpaSettings::dbBackupTables())],
            'gzip' => ['nullable', 'boolean'],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'tables.required' => 'Vyberte alespoň jednu tabulku k záloze.',
            'tables.*.in' => 'Neplatná tabulka.',
        ];
    }

    /**
     * Vybrané tabulky seřazené podle pořadí v allowlistu (kvůli FK při obnově).
     *
     * @return list<string>
     */
    public function selectedTables(): array
    {
        /** @var list<string> $selected */
        $selected = $this->validated()['tables'];

        return array_values(array_filter(
            VkvpaSettings::dbBackupTables(),
            static fn (string $table): bool => in_array($table, $selected, true),
        ));
    }

    /**
     * Má se výstup komprimovat gzipem (.sql.gz)?
     */
    public function wantsGzip(): bool
    {
        return $this->boolean('gzip');
    }
}
