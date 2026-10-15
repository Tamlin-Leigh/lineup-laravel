<?php

namespace App\Livewire\Events;

use App\Actions\FetchGoogleSheet;
use App\Actions\ImportAvailability;
use App\Models\User;
use App\Support\AvailabilityImportPreview;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The calendar's import modal: upload a CSV of free days, or paste a Google Sheets link, check what
 * will change, then save. Nothing is saved until the user confirms the preview.
 */
class AvailabilityImport extends Component
{
    use WithFileUploads;

    public const MODAL = 'import-availability';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $sheetUrl = '';

    /** @var list<string> The dates read from the file, Y-m-d. */
    #[Locked]
    public array $dates = [];

    /** @var list<array{line: int, value: string}> Rows that weren't a date. */
    #[Locked]
    public array $invalid = [];

    public function updatedFile(ImportAvailability $importAvailability): void
    {
        $this->reset('dates', 'invalid');

        $this->validate(
            ['file' => ['required', 'file', 'max:100', 'extensions:csv,txt', 'mimetypes:text/plain,text/csv,application/csv']],
            [
                'file.max' => __('The file must be smaller than 100 KB.'),
                'file.extensions' => __('Choose a .csv file.'),
                'file.mimetypes' => __('Choose a .csv file.'),
            ],
        );

        $contents = (string) file_get_contents($this->file->getRealPath());
        $this->reset('file');

        $this->read($importAvailability, $contents, 'file');
    }

    public function loadSheet(ImportAvailability $importAvailability, FetchGoogleSheet $fetchGoogleSheet): void
    {
        $this->reset('dates', 'invalid');
        $this->validate(['sheetUrl' => ['required', 'string', 'max:500']], ['sheetUrl.required' => __('Paste the link to your Google Sheet.')]);

        // Each load is a request from our server to Google, so keep it to a sensible pace.
        if (RateLimiter::tooManyAttempts($key = 'google-sheet:'.$this->user()->id, maxAttempts: 10)) {
            $this->addError('sheetUrl', __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key);

        try {
            $contents = $fetchGoogleSheet->handle($this->sheetUrl);
        } catch (RuntimeException $exception) {
            $this->addError('sheetUrl', $exception->getMessage());

            return;
        }

        $this->read($importAvailability, $contents, 'sheetUrl');
    }

    #[Computed]
    public function preview(): ?AvailabilityImportPreview
    {
        return $this->dates === [] ? null : app(ImportAvailability::class)->preview($this->user(), $this->dates);
    }

    public function import(ImportAvailability $importAvailability): void
    {
        if ($this->dates === []) {
            return;
        }

        $result = $importAvailability->apply($this->user(), $this->dates);
        $this->startOver();

        Flux::modal(self::MODAL)->close();
        Flux::toast(variant: 'success', text: __('Availability imported: :added added, :removed cleared.', $result));

        $this->dispatch('availability-updated');
    }

    public function startOver(): void
    {
        $this->reset('file', 'sheetUrl', 'dates', 'invalid');
        $this->resetErrorBag();
    }

    public function downloadTemplate(ImportAvailability $importAvailability): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($importAvailability->template()),
            'bandlink-free-days.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * Read the dates from a CSV (uploaded or from Google Sheets), reporting problems against the given field.
     */
    private function read(ImportAvailability $importAvailability, string $contents, string $field): void
    {
        $parsed = $importAvailability->parse($contents);

        if ($parsed['tooLong']) {
            $this->addError($field, __('The file has more than :count rows.', ['count' => ImportAvailability::MAX_ROWS]));

            return;
        }

        if ($parsed['dates'] === []) {
            $this->addError($field, __('No dates were found. Put one date per row in the first column, such as 2026-11-07 or 07/11/2026.'));

            return;
        }

        $this->dates = $parsed['dates'];
        $this->invalid = $parsed['invalid'];
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
