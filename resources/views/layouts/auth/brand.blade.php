{{--
    Split layout for every guest page (log in, sign up, password reset, verification), matching the
    React prototype: a card on the left, banner on the right. Light only, like the prototype.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>
            {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
        </title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-brand-ink antialiased">
        <div class="grid min-h-svh md:grid-cols-2">
            <main class="flex items-center justify-center bg-brand-tertiary/25 px-4 py-8">
                <div class="flex w-full max-w-md flex-col gap-6">
                    {{-- The banner (and its wordmark) is hidden on small screens. --}}
                    <p class="text-center text-3xl font-extrabold tracking-[-0.6px] text-brand md:hidden">{{ config('app.name', 'LineUp') }}</p>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-7 shadow-[0_20px_55px_rgba(0,0,0,0.08)]">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            {{-- Inline URL from the app: a Tailwind bg-[url()] resolves against the Vite dev server in development, which doesn't serve /public. --}}
            <aside class="relative hidden bg-cover bg-center md:block" style="background-image: url('{{ asset('images/SignUpBanner.jpg') }}')">
                <div class="absolute inset-0 bg-[rgba(10,22,22,0.55)] backdrop-blur-md"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center px-10 text-center text-white">
                    <p class="text-5xl font-extrabold tracking-[-1px]">{{ config('app.name', 'LineUp') }}</p>
                    <p class="mt-3 max-w-sm text-base/relaxed opacity-90">{{ __('Find all your band needs here.') }}</p>
                </div>
            </aside>
        </div>

        @fluxScripts
    </body>
</html>
