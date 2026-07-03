<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @vite('resources/js/face-onboarding.js')
    </head>
    <body class="min-h-screen bg-white antialiased">
        {{ $slot }}

        {{-- Bridge a redirect's session flash to a SweetAlert toast (see UI_STANDARDS.md). --}}
        @if (session()->has('swal'))
            <script>
                (function () {
                    var fire = function () {
                        window.dispatchEvent(new CustomEvent('swal', { detail: @json(session('swal')) }));
                    };
                    if (window.Swal) {
                        fire();
                    } else {
                        window.addEventListener('load', fire, { once: true });
                    }
                })();
            </script>
        @endif

        @fluxScripts
    </body>
</html>
