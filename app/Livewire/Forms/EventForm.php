<?php

namespace App\Livewire\Forms;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\VenueProfile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EventForm extends Form
{
    public string $title = '';

    public string $category = '';

    public string $description = '';

    /** A BandLink venue's profile id, or '' when the event is somewhere else. */
    public string $venue_profile_id = '';

    public string $location = '';

    public string $date = '';

    public string $time = '20:00';

    public string $price = '0';

    public string $status = 'draft';

    public function fillFromEvent(Event $event): void
    {
        $this->fill([
            'title' => $event->title,
            'category' => $event->category->value,
            'description' => (string) $event->description,
            'venue_profile_id' => (string) $event->venue_profile_id,
            'location' => $event->location,
            'date' => $event->starts_at->format('Y-m-d'),
            'time' => $event->starts_at->format('H:i'),
            'price' => (string) $event->price,
            'status' => $event->status->value,
        ]);
    }

    /**
     * Validate and return the attributes to save on the event.
     *
     * @return array<string, mixed>
     */
    public function attributes(bool $isNew): array
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(EventCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_profile_id' => ['nullable', 'integer', Rule::exists(VenueProfile::class, 'id')],
            'location' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            // An event can only be cancelled once it exists.
            'status' => ['required', $isNew
                ? Rule::enum(EventStatus::class)->except([EventStatus::Cancelled])
                : Rule::enum(EventStatus::class)],
        ]);

        return [
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => ($validated['description'] ?? '') ?: null,
            'venue_profile_id' => ($validated['venue_profile_id'] ?? '') ?: null,
            'location' => $validated['location'],
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['date']} {$validated['time']}"),
            'price' => (int) $validated['price'],
            'status' => $validated['status'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'venue_profile_id' => __('venue'),
        ];
    }
}
