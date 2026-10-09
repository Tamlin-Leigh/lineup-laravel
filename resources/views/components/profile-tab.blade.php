{{--
    One panel of the profile tabs. Shown/hidden in the browser by the surrounding x-data `tab`;
    the server-side `active` tab is rendered visible so there's no flash before Alpine starts.
--}}
@props(['name', 'active'])

<div
    role="tabpanel"
    id="profile-tab-{{ $name }}"
    x-show="tab === '{{ $name }}'"
    @style(['display: none' => $active !== $name])
    {{ $attributes->class('flex flex-col gap-5') }}
>
    {{ $slot }}
</div>
