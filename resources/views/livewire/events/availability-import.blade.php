@php
    use App\Livewire\Events\AvailabilityImport;

    $preview = $this->preview;
    $day = fn (string $date) => \Carbon\CarbonImmutable::parse($date)->format('D j M Y');
@endphp

{{-- display: contents keeps this wrapper out of the calendar's flex layout. --}}
<div class="contents">
    <flux:modal :name="AvailabilityImport::MODAL" class="w-full max-w-xl" x-on:close="$wire.startOver()">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('Import free days') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Upload a CSV file or use a Google Sheet, with one date per row listing only the days you are free. Use YYYY-MM-DD (2026-11-07) or day first (07/11/2026).') }}
                </flux:text>
            </div>

            @if ($preview === null)
                <flux:text>
                    {{ __('Between the first and last date in your list, your calendar will match it: listed days become free and any other free day in that period is cleared. Days outside it are not touched, so you can fix your spreadsheet and import it again at any time.') }}
                </flux:text>

                <div class="flex flex-col gap-2">
                    <flux:input type="file" wire:model="file" accept=".csv,text/csv,text/plain" :label="__('CSV file (Excel, Numbers, LibreOffice...)')" data-test="availability-csv-input" />
                    <div wire:loading wire:target="file" class="text-sm text-zinc-500">{{ __('Reading the file...') }}</div>
                </div>

                <div class="flex items-center gap-3 text-xs font-bold tracking-wide text-zinc-400 uppercase">
                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>{{ __('or') }}<span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                </div>

                <form wire:submit="loadSheet" class="flex flex-col gap-2">
                    <flux:field>
                        <flux:label>{{ __('Google Sheets link') }}</flux:label>
                        <div class="flex gap-2">
                            <flux:input wire:model="sheetUrl" type="url" placeholder="https://docs.google.com/spreadsheets/d/..." data-test="availability-sheet-input" />
                            <flux:button type="submit" data-test="load-sheet-button">{{ __('Load') }}</flux:button>
                        </div>
                        <flux:description>{{ __('In Google Sheets, click Share, set General access to "Anyone with the link", then copy the link. The dates are read from the first tab, or the tab open when you copied the link from the address bar. To avoid day and month mix-ups, format the dates as 2026-11-07 (Format > Number > Custom date and time).') }}</flux:description>
                        <flux:error name="sheetUrl" />
                    </flux:field>
                </form>

                <div class="flex items-center justify-between gap-3">
                    <flux:link wire:click="downloadTemplate" class="cursor-pointer text-sm">{{ __('Download an example file') }}</flux:link>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                </div>
            @else
                @if ($preview->from)
                    <flux:text>
                        {!! __('Your free days from :from to :to will match the file.', ['from' => '<strong>'.e($preview->from->format('D j M Y')).'</strong>', 'to' => '<strong>'.e($preview->to->format('D j M Y')).'</strong>']) !!}
                    </flux:text>
                @endif

                @if ($preview->pendingClashes !== [])
                    <flux:callout icon="exclamation-triangle" color="amber" data-test="import-pending-warning">
                        <flux:callout.heading>{{ __('You have booking requests waiting on days that will be cleared') }}</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="mt-1 list-disc ps-5">
                                @foreach ($preview->pendingClashes as $date => $titles)
                                    <li>{{ $day($date) }}: {{ implode(', ', $titles) }}</li>
                                @endforeach
                            </ul>
                            <span class="mt-2 block">{{ __('Clearing a day does not decline the request. Reply to it on the Bookings page so the other side is not left waiting.') }}</span>
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="flex flex-col gap-4 text-sm">
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ trans_choice(':count day will be marked free|:count days will be marked free', count($preview->add)) }}</div>
                        @if ($preview->add !== [])
                            <div class="mt-1.5 flex flex-wrap gap-1.5" data-test="import-add">
                                @foreach ($preview->add as $date)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">{{ $day($date) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ trans_choice(':count free day will be cleared|:count free days will be cleared', count($preview->remove)) }}</div>
                        @if ($preview->remove !== [])
                            <div class="mt-1.5 flex flex-wrap gap-1.5" data-test="import-remove">
                                @foreach ($preview->remove as $date)
                                    <span @class([
                                        'rounded-full px-2.5 py-0.5 text-xs font-bold',
                                        'bg-amber-100 text-amber-800' => isset($preview->pendingClashes[$date]),
                                        'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => ! isset($preview->pendingClashes[$date]),
                                    ])>{{ $day($date) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($preview->unchanged !== [])
                        <flux:text size="sm">{{ trans_choice(':count day in the file is already marked free.|:count days in the file are already marked free.', count($preview->unchanged)) }}</flux:text>
                    @endif

                    @if ($preview->busy !== [])
                        <flux:text size="sm">
                            {{ trans_choice('Skipped :count day you already have an event on:|Skipped :count days you already have an event on:', count($preview->busy)) }}
                            {{ implode(', ', array_map($day, $preview->busy)) }}
                        </flux:text>
                    @endif

                    @if ($preview->outOfRange !== [])
                        <flux:text size="sm">
                            {{ trans_choice('Skipped :count day that is in the past or more than two years ahead.|Skipped :count days that are in the past or more than two years ahead.', count($preview->outOfRange)) }}
                        </flux:text>
                    @endif

                    @if ($invalid !== [])
                        <flux:text size="sm" data-test="import-invalid">
                            {{ trans_choice('Ignored :count row that is not a date:|Ignored :count rows that are not dates:', count($invalid)) }}
                            {{ collect($invalid)->take(5)->map(fn ($row) => __('row :line (":value")', $row))->implode(', ') }}{{ count($invalid) > 5 ? ', ...' : '' }}
                        </flux:text>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="startOver">{{ __('Choose another file') }}</flux:button>
                    <flux:button variant="primary" wire:click="import" :disabled="! $preview->hasChanges()" data-test="confirm-import-button">
                        {{ $preview->hasChanges() ? __('Import') : __('Nothing to change') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
