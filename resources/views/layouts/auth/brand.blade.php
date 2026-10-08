{{-- Split sign-up layout matching the React prototype: form on the left, banner on the right. Light only. --}}
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
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </main>

            <aside class="relative hidden bg-[url('/images/SignUpBanner.jpg')] bg-cover bg-center md:block">
                <div class="absolute inset-0 bg-[rgba(10,22,22,0.55)] backdrop-blur-md"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center px-10 text-center text-white">
                    <p class="text-5xl font-extrabold tracking-[-1px]">{{ config('app.name', 'BandLink') }}</p>
                    <p class="mt-3 max-w-sm text-base/relaxed opacity-90">{{ __('Find all your band needs here.') }}</p>
                </div>
            </aside>
        </div>

        @fluxScripts
    </body>
</html>
