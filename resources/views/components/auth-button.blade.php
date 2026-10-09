{{--
    The full-width primary button used on every log in / sign up screen, sized like the prototype's.
    shrink-0: inside the sign-up card's scrolling column it would otherwise be squashed to fit.
    text-base!: Flux adds text-sm, which comes later in the stylesheet and would win.
--}}
<flux:button type="submit" variant="primary" {{ $attributes->class('h-12 w-full shrink-0 text-base! font-bold') }}>
    {{ $slot }}
</flux:button>
