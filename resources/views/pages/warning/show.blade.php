<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\WarningStatus;
use App\Models\WarningLetter;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Surat Peringatan')] class extends Component {
    public WarningLetter $warningLetter;

    public string $note = '';

    public function mount(WarningLetter $warningLetter): void
    {
        abort_unless($this->canView($warningLetter), 403);

        $this->warningLetter = $warningLetter->load(['student.classroom', 'decider']);
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageWarning->value);
    }

    public function approve(): void
    {
        abort_unless($this->canManage() && $this->warningLetter->isPending(), 403);

        $this->warningLetter->approve(auth()->user(), $this->note !== '' ? $this->note : null);

        toast(__('Surat peringatan diterbitkan.'), 'success');

        $this->redirectRoute('warnings.show', $this->warningLetter, navigate: true);
    }

    public function reject(): void
    {
        abort_unless($this->canManage() && $this->warningLetter->isPending(), 403);

        $this->validate(
            ['note' => ['required', 'string', 'max:255']],
            [],
            ['note' => __('alasan penolakan')],
        );

        $this->warningLetter->reject(auth()->user(), $this->note);

        toast(__('Rekomendasi SP ditolak.'), 'success');

        $this->redirectRoute('warnings.index', navigate: true);
    }

    /**
     * Siswa/Orang Tua only see issued letters; Wali Kelas is scoped to the
     * homeroom; other viewer roles see everything.
     */
    private function canView(WarningLetter $letter): bool
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => $letter->isIssued() && $user->student?->id === $letter->student_id,
            UserRole::OrangTua => $letter->isIssued()
                && (bool) $user->parentGuardian?->students()->whereKey($letter->student_id)->exists(),
            UserRole::WaliKelas => $letter->student?->classroom_id !== null
                && (bool) $user->teacher?->homeroomClassrooms()->whereKey($letter->student->classroom_id)->exists(),
            default => true,
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="$warningLetter->level->letterTitle()" :subtitle="$warningLetter->student?->name">
        <x-slot:actions>
            @if ($warningLetter->isIssued())
                <a href="{{ route('warnings.print', $warningLetter) }}" target="_blank"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">
                    <ion-icon name="print-outline" class="text-lg"></ion-icon> {{ __('Cetak') }}
                </a>
            @endif
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('warnings.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Detail --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Detail Surat') }}</h2>
                <span @class([
                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-amber-100 text-amber-700' => $warningLetter->status === WarningStatus::Pending,
                    'bg-green-100 text-green-700' => $warningLetter->status === WarningStatus::Approved,
                    'bg-red-100 text-red-700' => $warningLetter->status === WarningStatus::Rejected,
                ])>{{ $warningLetter->status->label() }}</span>
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Siswa') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $warningLetter->student?->name ?? '—' }}
                        <span class="text-gray-400">({{ $warningLetter->student?->classroom?->name ?? '—' }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Level') }}</dt>
                    <dd><x-warning.level-badge :level="$warningLetter->level" /></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Poin Saat Rekomendasi') }}</dt>
                    <dd class="font-medium text-gray-800 tabular-nums">{{ $warningLetter->points_snapshot }}
                        <span class="text-xs text-gray-400">({{ __('ambang :level ≤ :threshold', ['level' => $warningLetter->level->label(), 'threshold' => $warningLetter->threshold]) }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Poin Saat Ini') }}</dt>
                    <dd class="font-medium text-gray-800 tabular-nums">{{ $warningLetter->student?->current_point ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Nomor Surat') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $warningLetter->letter_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Direkomendasikan Pada') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $warningLetter->created_at?->translatedFormat('d M Y H:i') }}</dd>
                </div>
                @if ($warningLetter->decision_note)
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500">{{ __('Catatan Keputusan') }}</dt>
                        <dd class="text-gray-800">{{ $warningLetter->decision_note }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Decision panel --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Persetujuan Guru BK') }}</h2>

            @if (! $warningLetter->isPending())
                <p class="mt-2 text-sm text-gray-500">
                    {{ $warningLetter->status === WarningStatus::Approved ? __('Diterbitkan oleh') : __('Ditolak oleh') }}
                    <span class="font-medium text-gray-700">{{ $warningLetter->decider?->name ?? '—' }}</span>
                    {{ $warningLetter->decided_at ? '· '.$warningLetter->decided_at->translatedFormat('d M Y H:i') : '' }}
                </p>
            @elseif ($this->canManage())
                <div class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-semibold text-gray-600">{{ __('Catatan') }}</label>
                        <textarea wire:model="note" rows="2" placeholder="{{ __('Opsional saat menerbitkan, wajib bila menolak.') }}"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                        @error('note')
                            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <x-ui.button variant="primary" icon="checkmark-outline" wire:click="approve" class="w-full justify-center">
                        {{ __('Setujui & Terbitkan') }}
                    </x-ui.button>

                    <button type="button" wire:click="reject"
                        class="w-full rounded-md border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                        {{ __('Tolak Rekomendasi') }}
                    </button>

                    <p class="text-xs text-gray-500">
                        {{ __('Menyetujui akan memberi nomor surat dan membuka halaman cetak untuk disampaikan kepada orang tua/wali.') }}
                    </p>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">{{ __('Menunggu persetujuan Guru BK.') }}</p>
            @endif
        </div>
    </div>
</div>
