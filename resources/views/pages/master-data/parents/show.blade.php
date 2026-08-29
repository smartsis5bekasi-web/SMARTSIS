<?php

use App\Models\ParentGuardian;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Orang Tua')] class extends Component {
    public ParentGuardian $parent;

    public function mount(ParentGuardian $parent): void
    {
        $this->parent = $parent->load(['user', 'students.classroom']);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Detail Orang Tua')" :subtitle="$parent->name">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.parents.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="create-outline" :href="route('master-data.parents.edit', $parent)" wire:navigate>
                {{ __('Ubah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="flex flex-col items-center gap-4 rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm lg:col-span-1">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-primary-50 text-3xl font-bold text-primary-600">
                {{ $parent->user?->initials() ?? '—' }}
            </div>

            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $parent->name }}</h2>
                <p class="text-sm text-gray-500">{{ __('Orang Tua / Wali') }}</p>
            </div>

            <div class="flex flex-col items-center gap-2 border-t border-gray-100 pt-4 w-full">
                <div class="flex items-center justify-between w-full text-sm">
                    <span class="font-medium text-gray-600">{{ __('Status Akun') }}</span>
                    <x-ui.status-toggle :user="$parent->user" />
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="font-semibold text-gray-700">{{ __('Informasi Kontak') }}</h3>

            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Email') }}</span>
                    <span class="text-sm text-gray-800">{{ $parent->user?->email ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Telepon') }}</span>
                    <span class="text-sm text-gray-800">{{ $parent->phone ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 font-semibold text-gray-700">{{ __('Anak') }}</h3>

     @forelse ($parent->students as $student)
    <div wire:key="student-{{ $student->id }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-3 last:border-0">
        <div class="flex items-center gap-3">
            <img class="h-10 w-10 rounded-full border border-gray-200 object-cover" src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}">
            <div class="flex flex-col">
                <span class="font-medium text-gray-800">{{ $student->name }}</span>
                <span class="text-xs text-gray-400">{{ $student->classroom?->name ?? '—' }}</span>
            </div>
        </div>
        <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">
            {{ $student->pivot->relationship ?? '—' }}
        </span>
    </div>
        @empty
            <p class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-sm text-gray-500">
                {{ __('Belum terhubung dengan siswa manapun.') }}
            </p>
        @endforelse
    </div>
</div>