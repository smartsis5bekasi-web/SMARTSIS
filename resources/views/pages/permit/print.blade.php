<?php

use App\Enums\UserRole;
use App\Models\Permit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')] #[Title('Cetak Laporan Perizinan')] class extends Component {
    public string $search = '';
    public string $status = '';
    public string $type = '';

    public function mount(): void
    {
        $this->search = request('search', '');
        $this->status = request('status', '');
        $this->type = request('type', '');
    }

    public function getPermitsProperty(): Collection
    {
        return $this->scopedQuery()
            ->with(['student.classroom', 'decider'])
            ->when(trim($this->search) !== '', function (Builder $query) {
                $searchTerm = '%' . trim($this->search) . '%';
                $query->whereHas('student', function (Builder $q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                      ->orWhere('nis', 'like', $searchTerm);
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('type', $this->type))
            ->latest()
            ->get();
    }

    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Permit::query()->where('student_id', $user->student?->id ?? 0),
            UserRole::OrangTua => Permit::query()->whereIn(
                'student_id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            UserRole::WaliKelas => Permit::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            default => Permit::query(),
        };
    }
}; ?>

<div class="mx-auto max-w-6xl p-6">
    {{-- Header Tombol (Tersembunyi Saat Dicetak) --}}
    <div class="mb-6 flex items-center justify-between print:hidden">
        <a href="{{ route('permits.index') }}" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
            &larr; Kembali
        </a>
        <button 
            type="button" 
            onclick="window.print()" 
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-600 focus:outline-none"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Cetak / Simpan PDF
        </button>
    </div>

    {{-- Kertas Dokumen Laporan --}}
    <div class="rounded-lg bg-white p-8 shadow-sm border border-gray-200 print:border-none print:p-0 print:shadow-none">
        <div class="mb-6 border-b-2 border-gray-800 pb-4 text-center">
            <h2 class="text-xl font-bold uppercase tracking-wide text-gray-900">LAPORAN REKAPITULASI PERIZINAN SISWA</h2>
            <p class="mt-1 text-sm text-gray-600">SMAN 5 Bekasi — SMARTSIS</p>
            <p class="mt-1 text-xs text-gray-500">Tanggal Cetak: {{ now()->translatedFormat('d F Y') }}</p>
        </div>

        <table class="w-full border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border border-gray-300 p-2 text-center w-12">No</th>
                    <th class="border border-gray-300 p-2 text-left">Nama Siswa</th>
                    <th class="border border-gray-300 p-2 text-left">Kelas</th>
                    <th class="border border-gray-300 p-2 text-left">Jenis Izin</th>
                    <th class="border border-gray-300 p-2 text-left">Tanggal</th>
                    <th class="border border-gray-300 p-2 text-left">Alasan</th>
                    <th class="border border-gray-300 p-2 text-center">Status</th>
                    <th class="border border-gray-300 p-2 text-left">Diputuskan Oleh</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->permits as $index => $permit)
                    <tr>
                        <td class="border border-gray-300 p-2 text-center">{{ $index + 1 }}</td>
                        <td class="border border-gray-300 p-2 font-medium">{{ $permit->student?->name ?? '—' }}</td>
                        <td class="border border-gray-300 p-2">{{ $permit->student?->classroom?->name ?? '—' }}</td>
                        <td class="border border-gray-300 p-2">{{ $permit->type->label() }}</td>
                        <td class="border border-gray-300 p-2">{{ $permit->date->translatedFormat('d M Y') }}</td>
                        <td class="border border-gray-300 p-2 text-gray-600">{{ $permit->reason ?? '—' }}</td>
                        <td class="border border-gray-300 p-2 text-center font-semibold">
                            {{ $permit->status->label() }}
                        </td>
                        <td class="border border-gray-300 p-2 text-gray-600">{{ $permit->decider?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="border border-gray-300 p-4 text-center text-gray-500">
                            Tidak ada data perizinan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>