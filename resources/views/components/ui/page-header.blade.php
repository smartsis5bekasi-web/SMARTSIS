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
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
