{{-- Music links are band-only: include with ['socialOnly' => true] to show just the social repeater. --}}
@unless ($socialOnly ?? false)
    <x-link-repeater field="music_links" :kind="App\Enums\LinkKind::Music" :label="__('Music Links (optional)')" :add-label="__('Add music link')" />
@endunless

<x-link-repeater field="social_links" :kind="App\Enums\LinkKind::Social" :label="__('Social Links (optional)')" :add-label="__('Add social link')" />
