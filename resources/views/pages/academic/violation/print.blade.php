<?php

use App\Enums\PointApprovalStatus;
use App\Enums\UserRole;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')] #[Title('Cetak Laporan Pelanggaran Siswa')] class extends Component {
    public string $search = '';
    public string $status = '';

    public function mount(): void
    {
        $this->search = request('search', '');
        $this->status = request('status', '');
    }

    public function getViolationsProperty(): Collection
    {
        return $this->scopedQuery()
            ->with(['student.classroom', 'pointRule'])
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $q) use ($term) {
                    $q->whereHas('student', fn (Builder $sq) => $sq->where('name', 'like', $term)->orWhere('nis', 'like', $term))
                      ->orWhereHas('pointRule', fn (Builder $pq) => $pq->where('name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest()
            ->get();
    }

    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => Violation::query()->where('student_id', $user->student?->id),
            UserRole::WaliKelas => Violation::query()->whereHas(
                'student',
                fn (Builder $q) => $q->whereIn('classroom_id', $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect()),
            ),
            UserRole::OrangTua => Violation::query()->whereIn(
                'student_id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            default => Violation::query(),
        };
    }
}; ?>

<div class="mx-auto max-w-6xl p-6">
    <div class="mb-6 flex items-center justify-between print:hidden">
        <a href="{{ route('academic.violations') }}" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
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

    <div class="rounded-lg bg-white p-8 shadow-sm border border-gray-200 print:border-none print:p-0 print:shadow-none">
        <div class="mb-6 border-b-2 border-gray-800 pb-4 text-center">
            <h2 class="text-xl font-bold uppercase tracking-wide text-gray-900">LAPORAN REKAPITULASI PELANGGARAN SISWA</h2>
            <p class="mt-1 text-sm text-gray-600">SMAN 5 Bekasi — SMARTSIS</p>
            <p class="mt-1 text-xs text-gray-500">Tanggal Cetak: {{ now()->translatedFormat('d F Y') }}</p>
        </div>

        <table class="w-full border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border border-gray-300 p-2 text-center w-10">No</th>
                    <th class="border border-gray-300 p-2 text-left">Siswa</th>
                    <th class="border border-gray-300 p-2 text-left">Kelas</th>
                    <th class="border border-gray-300 p-2 text-left">Jenis Pelanggaran</th>
                    <th class="border border-gray-300 p-2 text-center">Poin</th>
                    <th class="border border-gray-300 p-2 text-center">Status</th>
                    <th class="border border-gray-300 p-2 text-center">Tanggal Kejadian</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->violations as $index => $violation)
                    <tr>
                        <td class="border border-gray-300 p-2 text-center">{{ $index + 1 }}</td>
                        <td class="border border-gray-300 p-2 font-medium">{{ $violation->student?->name ?? '—' }}</td>
                        <td class="border border-gray-300 p-2">{{ $violation->student?->classroom?->name ?? '—' }}</td>
                        <td class="border border-gray-300 p-2 font-medium">{{ $violation->pointRule?->name ?? '—' }}</td>
                        <td class="border border-gray-300 p-2 text-center font-bold text-red-600">
                            -{{ $violation->pointRule?->point ?? 0 }}
                        </td>
                        <td class="border border-gray-300 p-2 text-center font-semibold">
                            {{ $violation->status->label() }}
                        </td>
                        <td class="border border-gray-300 p-2 text-center">
                            {{ $violation->occurred_on?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="border border-gray-300 p-4 text-center text-gray-500">
                            Tidak ada data pelanggaran.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>