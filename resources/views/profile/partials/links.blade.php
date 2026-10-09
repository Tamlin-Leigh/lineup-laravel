{{-- Expects $profile (BandProfile|OrganiserProfile|null) and optional $socialOnly. --}}
@php use App\Enums\LinkKind; @endphp

<x-profile-section :title="__('Links')">
    @if ($editing)
        @include('partials.profile-fields.links', ['socialOnly' => $socialOnly ?? false])
    @elseif ($profile && $profile->links->isNotEmpty())
        @foreach (LinkKind::cases() as $kind)
            @php $links = $profile->links->where('kind', $kind); @endphp

            @if ($links->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-bold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $kind->label() }}</span>
                    <ul class="flex flex-col gap-2">
                        @foreach ($links as $link)
                            <li class="flex items-center gap-2.5">
                                <x-platform-icon :platform="$link->platform" class="size-5" />
                                <flux:link href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->platform->label() }}</flux:link>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    @else
        <flux:text>{{ __('No links added yet.') }}</flux:text>
    @endif
</x-profile-section>
