<?php

use App\Enums\Permission;
use App\Enums\PointApprovalStatus;
use App\Enums\PointSource;
use App\Enums\UserRole;
use App\Models\Achievement;
use App\Models\PointRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Prestasi')] class extends Component {
    public Achievement $achievement;

    public ?int $point_rule_id = null;

    public string $note = '';

    public function mount(Achievement $achievement): void
    {
        abort_unless($this->canView($achievement), 403);

        $this->achievement = $achievement->load(['student.classroom', 'pointRule', 'inputter', 'verifier']);
        $this->point_rule_id = $achievement->point_rule_id;
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::ManageAchievement->value);
    }

    /**
     * @return Collection<int, PointRule>
     */
    #[Computed]
    public function ruleOptions(): Collection
    {
        return PointRule::query()
            ->where('source', PointSource::Achievement)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function approve(): void
    {
        abort_unless($this->canManage() && $this->achievement->isPending(), 403);

        $this->validate([
            'point_rule_id' => ['required', Rule::exists('point_rules', 'id')->where('source', PointSource::Achievement->value)->where('is_active', true)],
        ]);

        // Let the approver correct the chosen jenis prestasi before points apply.
        if ($this->point_rule_id !== $this->achievement->point_rule_id) {
            $this->achievement->update(['point_rule_id' => $this->point_rule_id]);
        }

        $this->achievement->approve(auth()->user());

        toast(__('Prestasi disetujui, poin ditambahkan.'), 'success');

        $this->redirectRoute('academic.achievements', navigate: true);
    }

    public function reject(): void
    {
        abort_unless($this->canManage() && $this->achievement->isPending(), 403);

        $this->validate(['note' => ['required', 'string', 'max:255']]);

        $this->achievement->reject(auth()->user(), $this->note);

        toast(__('Prestasi ditolak.'), 'success');

        $this->redirectRoute('academic.achievements', navigate: true);
    }

    /**
     * Pending records are editable by their owner or a manager.
     */
    public function canEdit(): bool
    {
        return $this->achievement->isPending()
            && ($this->canManage() || auth()->user()->student?->id === $this->achievement->student_id);
    }

    /**
     * Managers may delete any record; a student may delete their own pending one.
     */
    public function canDelete(): bool
    {
        return $this->canManage()
            || ($this->achievement->isPending() && auth()->user()->student?->id === $this->achievement->student_id);
    }

    public function delete(): void
    {
        abort_unless($this->canDelete(), 403);

        // Undo any points granted before removing the record.
        if ($this->achievement->status === PointApprovalStatus::Approved) {
            $this->achievement->reversePoints(auth()->user());
        }

        $this->achievement->delete();

        toast(__('Prestasi dihapus.'), 'success');

        $this->redirectRoute('academic.achievements', navigate: true);
    }

    private function canView(Achievement $achievement): bool
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => $user->student?->id === $achievement->student_id,
            UserRole::OrangTua => (bool) $user->parentGuardian?->students()->whereKey($achievement->student_id)->exists(),
            UserRole::WaliKelas => $achievement->student?->classroom_id !== null
                && (bool) $user->teacher?->homeroomClassrooms()->whereKey($achievement->student->classroom_id)->exists(),
            default => true,
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="$achievement->title" :subtitle="$achievement->student?->name">
        <x-slot:actions>
            @if ($this->canEdit())
                <x-ui.button variant="secondary" icon="create-outline" :href="route('academic.achievements.edit', $achievement)" wire:navigate>
                    {{ __('Edit') }}
                </x-ui.button>
            @endif
            @if ($this->canDelete())
                <button type="button" x-data
                    @click="confirmDelete(() => $wire.delete(), { text: '{{ __('Prestasi & poin terkait akan dihapus.') }}' })"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                    <ion-icon name="trash-outline" class="text-lg"></ion-icon> {{ __('Hapus') }}
                </button>
            @endif
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('academic.achievements')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Detail --}}
        <div class="lg:col-span-2 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Detail Prestasi') }}</h2>
                <span @class([
                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-amber-100 text-amber-700' => $achievement->status === PointApprovalStatus::Pending,
                    'bg-green-100 text-green-700' => $achievement->status === PointApprovalStatus::Approved,
                    'bg-red-100 text-red-700' => $achievement->status === PointApprovalStatus::Rejected,
                ])>{{ $achievement->status->label() }}</span>
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Siswa') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $achievement->student?->name ?? '—' }} <span class="text-gray-400">({{ $achievement->student?->classroom?->name ?? '—' }})</span></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Jenis Prestasi') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $achievement->pointRule?->name ?? '—' }}
                        <span class="font-semibold text-green-600">(+{{ $achievement->pointRule?->point ?? 0 }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Tingkat') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $achievement->level ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500">{{ __('Tanggal') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $achievement->achieved_on?->translatedFormat('d M Y') ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-gray-500">{{ __('Deskripsi') }}</dt>
                    <dd class="text-gray-800">{{ $achievement->description ?: '—' }}</dd>
                </div>
                @if ($achievement->note)
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500">{{ __('Catatan Verifikasi') }}</dt>
                        <dd class="text-gray-800">{{ $achievement->note }}</dd>
                    </div>
                @endif
            </dl>

            @if ($achievement->evidence_path)
                <div class="mt-4">
                    <p class="mb-1 text-sm text-gray-500">{{ __('Bukti') }}</p>
                    @if (\Illuminate\Support\Str::endsWith(strtolower($achievement->evidence_path), '.pdf'))
                        <a href="{{ $achievement->evidence_path }}" target="_blank"
                            class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700">
                            <ion-icon name="document-text-outline" class="text-lg"></ion-icon> {{ __('Lihat dokumen') }}
                        </a>
                    @else
                        <a href="{{ $achievement->evidence_path }}" target="_blank">
                            <img src="{{ $achievement->evidence_path }}" alt="{{ __('Bukti') }}" class="max-h-64 rounded-lg border border-gray-200">
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Verification panel --}}
        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Verifikasi') }}</h2>

            @if ($achievement->status !== PointApprovalStatus::Pending)
                <p class="mt-2 text-sm text-gray-500">
                    {{ $achievement->status === PointApprovalStatus::Approved ? __('Disetujui oleh') : __('Ditolak oleh') }}
                    <span class="font-medium text-gray-700">{{ $achievement->verifier?->name ?? '—' }}</span>
                </p>
            @elseif ($this->canManage())
                <div class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-semibold text-gray-600">{{ __('Jenis Prestasi') }}</label>
                        <x-slim-select wire:model="point_rule_id">
                            @foreach ($this->ruleOptions as $rule)
                                <option value="{{ $rule->id }}">{{ $rule->name }} (+{{ $rule->point }})</option>
                            @endforeach
                        </x-slim-select>
                        <span class="mt-1 text-xs text-gray-400">{{ __('Sesuaikan bila perlu sebelum menyetujui.') }}</span>
                        @error('point_rule_id')
                            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <x-ui.button variant="primary" icon="checkmark-outline" wire:click="approve" class="w-full justify-center">
                        {{ __('Setujui & Tambah Poin') }}
                    </x-ui.button>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="mb-1 block text-sm font-semibold text-gray-600">{{ __('Alasan Penolakan') }}</label>
                        <textarea wire:model="note" rows="2" placeholder="{{ __('Wajib diisi bila menolak.') }}"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                        @error('note')
                            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                        @enderror
                        <button type="button" wire:click="reject"
                            class="mt-2 w-full rounded-md border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                            {{ __('Tolak Pengajuan') }}
                        </button>
                    </div>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">{{ __('Menunggu verifikasi oleh Guru BK / Admin.') }}</p>
            @endif
        </div>
    </div>
</div>
