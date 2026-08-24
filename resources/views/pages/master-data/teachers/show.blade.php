<?php

use App\Models\Teacher;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Guru')] class extends Component {
    public Teacher $teacher;

    public function mount(Teacher $teacher): void
    {
        $this->teacher = $teacher->load(['user.roles', 'homeroomClassrooms']);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Detail Guru')" :subtitle="$teacher->name">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.teachers')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="create-outline" :href="route('master-data.teachers.edit', $teacher)" wire:navigate>
                {{ __('Ubah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kartu Profil --}}
        <div class="flex flex-col items-center gap-4 rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm lg:col-span-1">
            <img class="h-32 w-32 rounded-2xl border border-gray-200 object-cover shadow-sm"
                src="{{ $teacher->avatar_url ?? asset('assets/placeholder.png') }}"
                alt="{{ $teacher->name }}">

            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $teacher->name }}</h2>
                <p class="text-sm text-gray-500">{{ $teacher->nip ?? '—' }}</p>
            </div>

            @if ($teacher->user?->primaryRole())
                <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">
                    {{ $teacher->user->primaryRole()->label() }}
                </span>
            @endif

            <div class="flex flex-col items-center gap-2 border-t border-gray-100 pt-4 w-full">
                <div class="flex items-center justify-between w-full text-sm">
                    <span class="font-medium text-gray-600">{{ __('Status Akun') }}</span>
                    <x-ui.status-toggle :user="$teacher->user" />
                </div>
            </div>
        </div>

        {{-- Kartu Detail --}}
        <div class="flex flex-col gap-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="font-semibold text-gray-700">{{ __('Informasi Guru') }}</h3>

            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Email') }}</span>
                    <span class="text-sm text-gray-800">{{ $teacher->user?->email ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Telepon') }}</span>
                    <span class="text-sm text-gray-800">{{ $teacher->phone ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">NIP</span>
                    <span class="text-sm text-gray-800">{{ $teacher->nip ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Peran') }}</span>
                    <span class="text-sm text-gray-800">{{ $teacher->user?->primaryRole()?->label() ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Kartu Kelas Perwalian (kalau ada) --}}
    @if ($teacher->homeroomClassrooms->isNotEmpty())
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-700">{{ __('Kelas Perwalian') }}</h3>

            @foreach ($teacher->homeroomClassrooms as $classroom)
                <div wire:key="classroom-{{ $classroom->id }}" class="flex items-center justify-between border-b border-gray-100 py-3 last:border-0">
                    <p class="font-medium text-gray-800">{{ $classroom->name }}</p>
                    <span class="text-sm text-gray-500">{{ $classroom->students_count ?? $classroom->students()->count() }} {{ __('siswa') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>