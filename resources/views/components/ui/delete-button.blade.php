@props([
    'wireId',          // model id passed to the Livewire method
    'method' => 'delete',
    'title' => null,   // optional SweetAlert title override
    'text' => null,    // optional SweetAlert text override
])

{{--
    Table-row delete action. Always confirms via SweetAlert first (see
    LIBRARY.md), then calls the Livewire `$method($wireId)` only on confirm.

        <x-ui.delete-button :wire-id="$teacher->id"
            :text="__('Akun login terkait juga akan dihapus.')" />
--}}
@php
    $options = array_filter([
        'title' => $title,
        'text' => $text,
    ], fn ($value) => filled($value));
@endphp

<button
    type="button"
    x-data
    @click="confirmDelete(() => $wire.{{ $method }}({{ $wireId }}), {{ Illuminate\Support\Js::from($options) }})"
    {{ $attributes->merge(['class' => 'inline-flex text-red-600 transition hover:text-red-700']) }}
    title="{{ __('Hapus') }}"
>
    <ion-icon name="trash-outline" class="text-xl"></ion-icon>
</button>
