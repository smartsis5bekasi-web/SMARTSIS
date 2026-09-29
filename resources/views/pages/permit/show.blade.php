<?php

use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\UserRole;
use App\Models\Permit;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Izin')] class extends Component {
    public Permit $permit;

    public string $note = '';

    public function mount(Permit $permit): void
    {
        abort_unless($this->canView($permit), 403);

        $this->permit = $permit->load(['student.classroom', 'decider', 'homeroomApprover']);
    }

    /**
     * Guru Piket / Super Admin give the decision that puts the permit into
     * effect (F-25).
     */
    public function canDecideAsPiket(): bool
    {
        return $this->permit->isPending()
            && auth()->user()->can(Permission::ManagePermit->value);
    }

    /**
     * The student's own wali kelas must approve every permit as well, before
     * or after the Guru Piket.
     */
    public function canDecideAsHomeroom(): bool
    {
        $user = auth()->user();

        return $this->permit->isAwaitingHomeroom()
            && $user->primaryRole() === UserRole::WaliKelas
            && $this->permit->student?->classroom_id !== null
            && (bool) $user->teacher?->homeroomClassrooms()->whereKey($this->permit->student->classroom_id)->exists();
    }

    public function canDecide(): bool
    {
        return $this->canDecideAsPiket() || $this->canDecideAsHomeroom();
    }

    /**
     * A siswa may cancel their own request while it is still pending.
     */
    public function canCancel(): bool
    {
        return $this->permit->isPending()
            && auth()->user()->student?->id === $this->permit->student_id;
    }

    public function approve(): void
    {
        $asPiket = $this->canDecideAsPiket();
        $asHomeroom = $this->canDecideAsHomeroom();

        abort_unless($asPiket || $asHomeroom, 403);

        $note = $this->note !== '' ? $this->note : null;

        if ($asPiket) {
            $this->permit->approve(auth()->user(), $note);
        }

        if ($asHomeroom) {
            $this->permit->approveAsHomeroom(auth()->user(), $note);
        }

        toast(__('Izin disetujui.'), 'success');

        $this->redirectRoute('permits.index', navigate: true);
    }

    public function reject(): void
    {
        abort_unless($this->canDecide(), 403);

        $this->validate(
            ['note' => ['required', 'string', 'max:255']],
            [],
            ['note' => __('alasan penolakan')],
        );

        $this->permit->reject(auth()->user(), $this->note);

        toast(__('Izin ditolak.'), 'success');

        $this->redirectRoute('permits.index', navigate: true);
    }

    public function cancel(): void
    {
        abort_unless($this->canCancel(), 403);

        $this->permit->delete();

        toast(__('Pengajuan izin dibatalkan.'), 'success');

        $this->redirectRoute('permits.index', navigate: true);
    }

    private function canView(Permit $permit): bool
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => $user->student?->id === $permit->student_id,
            UserRole::OrangTua => (bool) $user->parentGuardian?->students()->whereKey($permit->student_id)->exists(),
            UserRole::WaliKelas => $permit->student?->classroom_id !== null
                && (bool) $user->teacher?->homeroomClassrooms()->whereKey($permit->student->classroom_id)->exists(),
            default => true,
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="$permit->type->label()" :subtitle="$permit->student?->name">
        <x-slot:actions>
            @if ($this->canCancel())
                <button type="button" x-data
                    @click="confirmDelete(() => $wire.cancel(), {
                        title: @js(__('Batalkan pengajuan?')),
                        text: @js(__('Pengajuan izin yang dibatalkan tidak dapat dikembalikan.')),
                        confirmButtonText: @js(__('Ya, batalkan')),
                    })"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                    <ion-icon name="close-circle-outline" class="text-lg"></ion-icon> {{ __('Batalkan') }}
                </button>
            @endif
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('permits.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Detail --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Detail Pengajuan') }}</h2>
                <x-permit.status-badge :permit="$permit" />
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Siswa') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $permit->student?->name ?? '—' }}
                        <span class="text-gray-400">({{ $permit->student?->classroom?->name ?? '—' }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Jenis Izin') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $permit->type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Tanggal Izin') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $permit->date->translatedFormat('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Diajukan Pada') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $permit->created_at?->translatedFormat('d M Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-gray-500">{{ __('Alasan') }}</dt>
                    <dd class="text-gray-800">{{ $permit->reason }}</dd>
                </div>
                @if ($permit->decision_note)
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500">{{ __('Catatan Keputusan') }}</dt>
                        <dd class="text-gray-800">{{ $permit->decision_note }}</dd>
                    </div>
                @endif
                @if ($permit->homeroom_note)
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500">{{ __('Catatan Wali Kelas') }}</dt>
                        <dd class="text-gray-800">{{ $permit->homeroom_note }}</dd>
                    </div>
                @endif
            </dl>

            @if ($permit->attachment_path)
                <div class="mt-4">
                    <p class="mb-1 text-sm text-gray-500">{{ __('Lampiran') }}</p>
                    @if (\Illuminate\Support\Str::endsWith(strtolower($permit->attachment_path), '.pdf'))
                        <a href="{{ $permit->attachment_path }}" target="_blank"
                            class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700">
                            <ion-icon name="document-text-outline" class="text-lg"></ion-icon> {{ __('Lihat dokumen') }}
                        </a>
                    @else
                        <a href="{{ $permit->attachment_path }}" target="_blank">
                            <img src="{{ $permit->attachment_path }}" alt="{{ __('Lampiran') }}" class="max-h-64 rounded-lg border border-gray-200">
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Decision panel --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Persetujuan') }}</h2>

            @if ($permit->status === PermitStatus::Rejected)
                <p class="mt-2 text-sm text-gray-500">
                    {{ __('Ditolak oleh') }}
                    <span class="font-medium text-gray-700">{{ $permit->decider?->name ?? '—' }}</span>
                    {{ $permit->decided_at ? '· '.$permit->decided_at->translatedFormat('d M Y H:i') : '' }}
                </p>
            @else
                {{-- Both sign-offs are required: Guru Piket (puts the permit into effect) and Wali Kelas. --}}
                <ul class="mt-4 flex flex-col gap-3">
                    @foreach ([
                        [__('Guru Piket'), $permit->status === PermitStatus::Approved, $permit->decider, $permit->decided_at],
                        [__('Wali Kelas'), $permit->isHomeroomApproved(), $permit->homeroomApprover, $permit->homeroom_approved_at],
                    ] as [$approverRole, $isApproved, $approver, $approvedAt])
                        <li class="flex items-start gap-2 text-sm">
                            <ion-icon name="{{ $isApproved ? 'checkmark-circle' : 'time-outline' }}"
                                @class(['mt-0.5 shrink-0 text-lg', 'text-green-600' => $isApproved, 'text-amber-500' => ! $isApproved])></ion-icon>
                            <div>
                                <p class="font-semibold text-gray-700">{{ $approverRole }}</p>
                                @if ($isApproved)
                                    <p class="text-gray-500">
                                        {{ __('Disetujui oleh') }} <span class="font-medium text-gray-700">{{ $approver?->name ?? '—' }}</span>
                                        {{ $approvedAt ? '· '.$approvedAt->translatedFormat('d M Y H:i') : '' }}
                                    </p>
                                @else
                                    <p class="text-gray-500">{{ __('Menunggu persetujuan') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($this->canDecide())
                <div class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-semibold text-gray-600">{{ __('Catatan') }}</label>
                        <textarea wire:model="note" rows="2" placeholder="{{ __('Opsional saat menyetujui, wajib bila menolak.') }}"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                        @error('note')
                            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <x-ui.button variant="primary" icon="checkmark-outline" wire:click="approve" class="w-full justify-center">
                        {{ __('Setujui Izin') }}
                    </x-ui.button>

                    <button type="button" wire:click="reject"
                        class="w-full rounded-md border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                        {{ __('Tolak Izin') }}
                    </button>

                    {{-- Only the Guru Piket decision changes attendance. --}}
                    @if (! $this->canDecideAsPiket())
                        <p class="text-xs text-gray-500">{{ __('Persetujuan Anda sebagai wali kelas melengkapi persetujuan Guru Piket.') }}</p>
                    @elseif ($permit->type === App\Enums\PermitType::Terlambat)
                        <p class="text-xs text-gray-500">{{ __('Bila disetujui, keterlambatan siswa pada tanggal tersebut tidak mengurangi poin.') }}</p>
                    @elseif ($permit->type === App\Enums\PermitType::PulangAwal)
                        <p class="text-xs text-gray-500">{{ __('Bila disetujui, siswa dapat melakukan absensi pulang sebelum jam pulang pada tanggal tersebut.') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
