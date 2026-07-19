<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @vite('resources/js/face-onboarding.js')
    </head>
    <body class="min-h-screen bg-white antialiased">
        {{ $slot }}

        {{-- Renders alerts flashed via realrashid/sweet-alert's toast()/alert() helpers (see UI_STANDARDS.md). --}}
        @include('sweetalert::alert')

        @fluxScripts
    </body>
</html>
