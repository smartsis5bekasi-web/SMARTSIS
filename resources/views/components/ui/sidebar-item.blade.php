@props([
    'icon',
    'href',
    'active' => false,
])

{{--
    Pure-Tailwind sidebar nav item with an Ionicon. Stays highlighted across a
    whole section (index/create/edit) by passing a wildcard route check:

        <x-ui.sidebar-item icon="people-outline"
            :href="route('master-data.teachers')"
            :active="request()->routeIs('master-data.teachers*')">
            {{ __('Guru') }}
        </x-ui.sidebar-item>
--}}
<a
    href="{{ $href }}"
    wire:navigate
    @class([
        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
        'bg-primary-50 text-primary-700' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
    ])
>
    <ion-icon name="{{ $icon }}" class="text-xl"></ion-icon>
    <span>{{ $slot }}</span>
</a>
