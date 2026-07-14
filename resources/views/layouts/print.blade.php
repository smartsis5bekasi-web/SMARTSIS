<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            @media print {
                @page { size: A4; margin: 2cm; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-gray-100 antialiased print:bg-white">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
