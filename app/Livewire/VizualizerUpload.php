<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Exceptions\EdiParseException;
use App\Http\Controllers\VizualizerController;
use App\Services\Edi\EdiParser;
use App\Support\VkvpaSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Upload pro samostatný EDI Visualizer – stejná drag-and-drop UX jako podání
 * hlášení ({@see Prihlaska}): skrytý `<input wire:model>` v `.upload-zone`,
 * globální drop handler v app.js, stav „zpracovávám" přes `wire:loading`.
 *
 * Po nahrání se deník naparsuje (kontrola validity), uloží pod náhodným
 * tokenem do `storage/app/private/vizualizer/{token}.edi` a komponenta
 * přesměruje na sdílecí mapu ({@see VizualizerController}).
 * Nic se nepřidává do závodních dat.
 */
class VizualizerUpload extends Component
{
    use WithFileUploads;

    /** Adresář úložiště nahraných deníků (disk `local`) – shodně s controllerem. */
    private const string DIR = 'vizualizer';

    public mixed $upload = null;

    public string $errorMessage = '';

    /** @var list<string> */
    public array $lineErrors = [];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'upload' => ['required', 'file', 'max:'.VkvpaSettings::ediMaxSizeKb(), 'extensions:edi,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'upload.required' => __('pages.vizualizer.err_required'),
            'upload.extensions' => __('pages.vizualizer.err_ext'),
            'upload.max' => __('pages.vizualizer.err_max', ['max' => VkvpaSettings::ediMaxSizeKb()]),
        ];
    }

    /** Po výběru/dropnutí souboru: zvaliduj, naparsuj, ulož a přesměruj na mapu. */
    public function updatedUpload(): mixed
    {
        $this->errorMessage = '';
        $this->lineErrors = [];

        $this->validateOnly('upload');

        /** @var TemporaryUploadedFile $file */
        $file = $this->upload;
        $content = (string) file_get_contents($file->getRealPath());

        try {
            app(EdiParser::class)->parse($content);
        } catch (EdiParseException $e) {
            $this->errorMessage = $e->getMessage();
            $this->lineErrors = $e->lineErrors;
            $this->upload = null;

            return null;
        }

        $token = Str::random(16);
        Storage::disk('local')->put(self::DIR.'/'.$token.'.edi', $content);

        return $this->redirectRoute('vizualizer.show', ['token' => $token], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.vizualizer-upload');
    }
}
