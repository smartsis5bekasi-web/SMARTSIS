<?php

use App\Models\Student;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Siswa')] class extends Component {
    public Student $student;

    public function mount(Student $student): void
    {
        $this->student = $student->load(['classroom', 'major', 'teacher', 'parents', 'user']);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Detail Siswa')" :subtitle="$student->name">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.students.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="create-outline" :href="route('master-data.students.edit', $student)" wire:navigate>
                {{ __('Ubah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kartu Profil --}}
        <div class="flex flex-col items-center gap-4 rounded-xl border border-gray-100 bg-white p-6 text-center shadow-sm lg:col-span-1">
            <img class="h-32 w-32 rounded-2xl border border-gray-200 object-cover shadow-sm"
                src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}"
                alt="{{ $student->name }}">

            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $student->name }}</h2>
                <p class="text-sm text-gray-500">{{ $student->nis }}</p>
            </div>

            <div class="flex flex-col items-center gap-2 border-t border-gray-100 pt-4 w-full">
                <div class="flex items-center justify-between w-full text-sm">
                    <span class="font-medium text-gray-600">{{ __('Status Akun') }}</span>
                    <x-ui.status-toggle :user="$student->user" />
                </div>
                <div class="flex items-center justify-between w-full text-sm">
                    <span class="font-medium text-gray-600">{{ __('Poin Saat Ini') }}</span>
                    <span class="rounded-full bg-primary-50 px-3 py-1 font-semibold text-primary-700">{{ $student->current_point }}</span>
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-1.5 border-t border-gray-100 pt-4 w-full">
                @if ($student->hasCompletedOnboarding())
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                        <ion-icon name="checkmark-circle-outline"></ion-icon> {{ __('Onboarding Selesai') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-yellow-50 px-2.5 py-1 text-xs font-medium text-yellow-700">
                        <ion-icon name="time-outline"></ion-icon> {{ __('Belum Onboarding') }}
                    </span>
                @endif

                @if ($student->hasRegisteredFace())
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700">
                        <ion-icon name="scan-outline"></ion-icon> {{ __('Wajah Terdaftar') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Kartu Detail --}}
        <div class="flex flex-col gap-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="font-semibold text-gray-700">{{ __('Informasi Siswa') }}</h3>

            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Email') }}</span>
                    <span class="text-sm text-gray-800">{{ $student->user->email ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">NISN</span>
                    <span class="text-sm text-gray-800">{{ $student->nisn ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Jenis Kelamin') }}</span>
                    <span class="text-sm text-gray-800">
                        {{ $student->gender === 'L' ? __('Laki-laki') : ($student->gender === 'P' ? __('Perempuan') : '—') }}
                    </span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Tanggal Lahir') }}</span>
                    <span class="text-sm text-gray-800">
                        {{ $student->birth_date?->translatedFormat('d F Y') ?? '—' }}
                    </span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Kelas') }}</span>
                    <span class="text-sm text-gray-800">{{ $student->classroom?->name ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Jurusan') }}</span>
                    <span class="text-sm text-gray-800">{{ $student->major?->name ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500">{{ __('Wali Kelas') }}</span>
                    <span class="text-sm text-gray-800">{{ $student->teacher?->name ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1 sm:col-span-2">
                    <span class="text-xs font-medium text-gray-500">{{ __('Alamat') }}</span>
                    <span class="text-sm text-gray-800">{{ $student->address ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Kartu Orang Tua / Wali --}}
    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <h3 class="mb-4 font-semibold text-gray-700">{{ __('Data Orang Tua / Wali') }}</h3>

        @forelse ($student->parents as $parent)
            <div wire:key="parent-{{ $parent->id }}" class="flex flex-col justify-between gap-2 border-b border-gray-100 py-3 last:border-0 sm:flex-row sm:items-center">
                <div>
                    <p class="font-medium text-gray-800">{{ $parent->name }}</p>
                    <p class="text-xs text-gray-500">{{ $parent->pivot->relationship ?? '—' }}</p>
                </div>
                <p class="text-sm text-gray-600">{{ $parent->phone ?? '—' }}</p>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-sm text-gray-500">
                {{ __('Belum ada data orang tua/wali.') }}
            </p>
        @endforelse
    </div>
</div>