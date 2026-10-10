<?php

namespace App\Livewire\Forms;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EventForm extends Form
{
    public const MAX_BAND_SLOTS = 20;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $date = '';

    public string $time = '20:00';

    public string $price = '0';

    /** How many bands will play, the host band included. */
    public string $band_slots = '1';

    public string $status = 'draft';

    public function fillFromEvent(Event $event): void
    {
        $this->fill([
            'title' => $event->title,
            'description' => (string) $event->description,
            'location' => $event->location,
            'date' => $event->starts_at->format('Y-m-d'),
            'time' => $event->starts_at->format('H:i'),
            'price' => (string) $event->price,
            'band_slots' => (string) $event->band_slots,
            'status' => $event->status->value,
        ]);
    }

    /**
     * Validate some of the fields, e.g. one step of the new event wizard.
     *
     * @param  list<string>  $fields
     */
    public function validateFields(array $fields, bool $isNew = true): void
    {
        $this->validate(array_intersect_key($this->rulesFor($isNew), array_flip($fields)));
    }

    /**
     * Validate and return the attributes to save on the event.
     *
     * @return array<string, mixed>
     */
    public function attributes(bool $isNew): array
    {
        $validated = $this->validate($this->rulesFor($isNew));

        return [
            'title' => $validated['title'],
            'description' => ($validated['description'] ?? '') ?: null,
            'location' => $validated['location'],
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['date']} {$validated['time']}"),
            'price' => (int) $validated['price'],
            'band_slots' => (int) $validated['band_slots'],
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rulesFor(bool $isNew): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'band_slots' => ['required', 'integer', 'min:1', 'max:'.self::MAX_BAND_SLOTS],
            // An event can only be cancelled once it exists.
            'status' => ['required', $isNew
                ? Rule::enum(EventStatus::class)->except([EventStatus::Cancelled])
                : Rule::enum(EventStatus::class)],
        ];
    }
}
