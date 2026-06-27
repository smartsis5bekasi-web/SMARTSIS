@props([
    'variant' => 'primary',
    'icon' => null,
    'iconTrailing' => null,
    'href' => null,
])

{{--
    Standardized pure-Tailwind button (no Flux). Icons are Ionicons.

        <x-ui.button variant="primary" icon="add-outline" :href="route('...')" wire:navigate>
            {{ __('Tambah') }}
        </x-ui.button>

        <x-ui.button variant="primary" type="submit">{{ __('Simpan') }}</x-ui.button>

    Variants: primary (brand), secondary (neutral/outline), danger.
--}}
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-primary-300 disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700',
        'secondary' => 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <ion-icon name="{{ $icon }}" class="text-lg"></ion-icon>
        @endif
        {{ $slot }}
        @if ($iconTrailing)
            <ion-icon name="{{ $iconTrailing }}" class="text-lg"></ion-icon>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon)
            <ion-icon name="{{ $icon }}" class="text-lg"></ion-icon>
        @endif
        {{ $slot }}
        @if ($iconTrailing)
            <ion-icon name="{{ $iconTrailing }}" class="text-lg"></ion-icon>
        @endif
    </button>
@endif
