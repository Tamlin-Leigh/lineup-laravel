{{--
    Link repeater bound to a ProfileForm links field (form.music_links or form.social_links).
    Rows are added, removed and edited in the browser straight on $wire's copy of the field, so
    nothing waits for the server; the rows go up with the next request (Next / Save) and are
    validated there. wire:ignore keeps Livewire's re-renders from replacing the rows Alpine drew;
    they still follow $wire, so a server-side change to the field (e.g. Cancel) shows too.
--}}
@props([
    'field',
    'kind',
    'label',
    'addLabel',
])

@php
    $model = 'form.'.$field;
    $platforms = $kind->platforms();
@endphp

<div
    class="flex flex-col gap-2"
    role="group"
    aria-labelledby="{{ $field }}-label"
    wire:ignore
    x-data="{
        // wire:ignore also stops the server's error messages reaching these rows, so keep a copy of
        // the errors and refresh it after every response from this component.
        errors: $wire.$errors.messages(),
        init() {
            this.stopWatchingErrors = Livewire.hook('effect', ({ component }) => {
                if (component.id === $wire.$id) queueMicrotask(() => this.errors = { ...component.snapshot.memo.errors })
            })
        },
        destroy() { this.stopWatchingErrors?.() },
        get links() { return $wire.form.{{ $field }} },
        add() {
            if (this.links.length < {{ App\Livewire\Forms\ProfileForm::MAX_LINKS }}) {
                this.links.push({ platform: @js($platforms[0]->value), url: '' })
            }
        },
        remove(index) {
            this.links.splice(index, 1)
            // The rows below move up, so their errors would point at the wrong row.
            this.errors = Object.fromEntries(Object.entries(this.errors).filter(([key]) => ! key.startsWith('{{ $model }}')))
        },
        error(key) { return this.errors[key]?.[0] ?? '' },
    }"
    data-test="link-repeater-{{ $field }}"
>
    <flux:label id="{{ $field }}-label">{{ $label }}</flux:label>

    <template x-for="(link, index) in links" :key="index">
        <div>
            <div class="flex items-center gap-2">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-zinc-200 bg-white">
                    @foreach ($platforms as $platform)
                        <x-platform-icon :platform="$platform" class="size-5" x-show="link.platform === '{{ $platform->value }}'" />
                    @endforeach
                </span>

                <flux:select x-model="link.platform" class="max-w-36" aria-label="{{ __('Platform') }}">
                    @foreach ($platforms as $platform)
                        <flux:select.option value="{{ $platform->value }}">{{ $platform->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input x-model="link.url" type="url" placeholder="https://..." aria-label="{{ __('Link URL') }}" />

                <flux:button type="button" variant="ghost" icon="x-mark" x-on:click="remove(index)" aria-label="{{ __('Remove link') }}" />
            </div>
            <template x-for="key in ['platform', 'url']">
                <p class="mt-1 text-sm font-medium text-red-500 dark:text-red-400" x-show="error(`{{ $model }}.${index}.${key}`)" x-text="error(`{{ $model }}.${index}.${key}`)"></p>
            </template>
        </div>
    </template>

    <p class="text-sm font-medium text-red-500 dark:text-red-400" x-show="error('{{ $model }}')" x-text="error('{{ $model }}')"></p>

    <button
        type="button"
        x-show="links.length < {{ App\Livewire\Forms\ProfileForm::MAX_LINKS }}"
        x-on:click="add()"
        class="self-start rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm font-medium text-brand-secondary hover:border-brand-secondary dark:border-zinc-600"
    >
        + {{ $addLabel }}
    </button>
</div>
