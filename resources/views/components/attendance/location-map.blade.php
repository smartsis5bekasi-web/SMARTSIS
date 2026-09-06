@props([
    'latitude',
    'longitude',
    'accuracy' => null,
    'label' => null,
])

@php
    // A tight bounding box around the fix keeps the OpenStreetMap embed at
    // street level; no API key is needed, unlike the Google Maps embed.
    $delta = 0.0022;
    $bbox = implode(',', [
        $longitude - $delta,
        $latitude - $delta,
        $longitude + $delta,
        $latitude + $delta,
    ]);
@endphp

{{-- Precise position of a recorded absensi: map, exact coordinates, GPS radius. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <div class="overflow-hidden rounded-xl border border-gray-200">
        <iframe
            title="{{ $label ?? __('Lokasi Absen') }}"
            class="h-64 w-full"
            loading="lazy"
            referrerpolicy="no-referrer"
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $latitude }},{{ $longitude }}"></iframe>
    </div>

    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-xs uppercase tracking-wider text-gray-400">{{ __('Koordinat') }}</dt>
            <dd class="font-semibold tabular-nums text-gray-800">{{ number_format($latitude, 6, '.', '') }}, {{ number_format($longitude, 6, '.', '') }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wider text-gray-400">{{ __('Akurasi') }}</dt>
            <dd class="font-semibold tabular-nums text-gray-800">{{ $accuracy !== null ? '±'.$accuracy.' m' : '—' }}</dd>
        </div>
    </dl>

    <a href="https://www.google.com/maps/search/?api=1&query={{ $latitude }},{{ $longitude }}" target="_blank" rel="noopener"
        class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
        <ion-icon name="navigate-outline" class="text-lg"></ion-icon>
        {{ __('Buka di Google Maps') }}
    </a>
</div>
