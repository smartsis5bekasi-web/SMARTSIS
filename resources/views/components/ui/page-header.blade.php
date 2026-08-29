@props([
    'title',
    'subtitle' => null,
])

{{--
    Standard page heading row. Put page actions (e.g. a "Tambah" button or a
    "Kembali" link) in the `actions` slot:

        <x-ui.page-header :title="__('Guru')" :subtitle="__('Kelola data guru.')">
            <x-slot:actions>
                <x-ui.button variant="primary" icon="add-outline" :href="route('...')" wire:navigate>
                    {{ __('Tambah') }}
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>
--}}
<div class="flex w-full min-w-0 flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold text-gray-800">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    {{-- Wraps: a page with four actions would otherwise push the whole layout
         wider than a phone screen and scroll the body sideways. --}}
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
