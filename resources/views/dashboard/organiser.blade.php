<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        @include('dashboard.availability')
    </div>

    <div class="md:col-span-2">
        @include('dashboard.upcoming-events')
    </div>

    <x-profile-section :title="$organiser->org_name">
        <flux:link href="tel:{{ $organiser->contact_number }}">{{ $organiser->contact_number }}</flux:link>
        <flux:link href="mailto:{{ $organiser->contact_email }}">{{ $organiser->contact_email }}</flux:link>
    </x-profile-section>

    @if ($organiser->links->isNotEmpty())
        <x-profile-section :title="__('Links')">
            <ul class="flex flex-col gap-2">
                @foreach ($organiser->links as $link)
                    <li class="flex items-center gap-2.5">
                        <x-platform-icon :platform="$link->platform" class="size-5" />
                        <flux:link href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->platform->label() }}</flux:link>
                    </li>
                @endforeach
            </ul>
        </x-profile-section>
    @endif
</div>
