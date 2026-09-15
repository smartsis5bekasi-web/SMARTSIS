<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    {{-- Full-screen shell for the classroom attendance tablet: no sidebar, no menus. --}}
    <body class="min-h-screen bg-gray-100 antialiased">
        {{ $slot }}

        {{-- Renders alerts flashed via realrashid/sweet-alert's toast()/alert() helpers (see UI_STANDARDS.md). --}}
        @include('sweetalert::alert')

        @fluxScripts
    </body>
</html>
