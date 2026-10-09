{{--
    A phone number field that only accepts digits and a leading "+" as you type (letters, symbols
    and extra "+" signs are removed immediately). The server validates the format again.
--}}
<flux:input
    type="tel"
    inputmode="tel"
    autocomplete="tel"
    maxlength="16"
    x-on:input="
        const cleaned = $el.value.replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
        if (cleaned !== $el.value) { $el.value = cleaned; $el.dispatchEvent(new Event('input')); }
    "
    {{ $attributes }}
/>
