<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UnknownSectionException;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiValidator;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Livewire komponenta pro nahrání EDI deníku.
 *
 * Stavy: idle → uploading → success | error
 * Po úspěšném zpracování uloží owned_data_id do session a zobrazí souhrn
 * s tlačítkem pro přechod na formulář hlášení.
 */
class EdiUpload extends Component
{
    use WithFileUploads;

    #[Rule('required|file|mimes:edi,txt|max:10240')]
    public mixed $upload = null;

    public string $state = 'idle';

    public string $errorMessage = '';

    /** @var list<string> */
    public array $lineErrors = [];

    /** @var list<string> */
    public array $warnings = [];

    public int $dataId = 0;

    public string $pcall = '';

    public string $band = '';

    public int $pocet = 0;

    public int $body = 0;

    public function updatedUpload(): void
    {
        $this->validateOnly('upload');
        $this->process();
    }

    private function process(): void
    {
        $this->state = 'uploading';
        $this->errorMessage = '';
        $this->lineErrors = [];
        $this->warnings = [];

        try {
            /** @var TemporaryUploadedFile $file */
            $file = $this->upload;
            $content = (string) file_get_contents($file->getRealPath());

            $parser = app(EdiParser::class);
            $action = app(ImportEdiAction::class);
            $validator = app(EdiValidator::class);

            try {
                $log = $parser->parse($content);
            } catch (EdiParseException $e) {
                $this->state = 'error';
                $this->errorMessage = $e->getMessage();
                $this->lineErrors = $e->lineErrors;

                return;
            }

            try {
                $row = $action->execute($log);
            } catch (
                TDateNotContestDayException|RoundNotFoundException|TDateMismatchException|
                DuplicateEdiException|UnknownBandException|UnknownSectionException $e
            ) {
                $this->state = 'error';
                $this->errorMessage = $e->getMessage();

                return;
            }

            session(['owned_data_id' => $row->id]);

            $this->warnings = $validator->validate($log)->messages();
            $this->dataId = $row->id;
            $this->pcall = $log->header->pCall();
            $this->band = $log->header->pBand();
            $this->pocet = $row->pocet ?? 0;
            $this->body = $row->body ?? 0;
            $this->state = 'success';

        } catch (Throwable $e) {
            $this->state = 'error';
            $this->errorMessage = $e->getMessage();
        }
    }

    public function resetForm(): void
    {
        $this->state = 'idle';
        $this->upload = null;
        $this->errorMessage = '';
        $this->lineErrors = [];
        $this->warnings = [];
        $this->dataId = 0;
        $this->pcall = '';
    }
}
