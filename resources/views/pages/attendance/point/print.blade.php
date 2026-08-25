<?php

use App\Enums\UserRole;
use App\Models\PointSetting;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')] #[Title('Cetak Monitoring Poin')] class extends Component {
    public string $search = '';
    public string $classroomId = '';
    public string $status = '';

    public function mount(): void
    {
        $this->search = request('search', '');
        $this->classroomId = request('classroomId', '');
        $this->status = request('status', '');
    }

    public function getSettingProperty(): PointSetting
    {
        return PointSetting::current();
    }

    public function getStudentsProperty(): Collection
    {
        $setting = $this->setting;

        return $this->scopedQuery()
            ->with(['classroom'])
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('nis', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->classroomId !== '', fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when($this->status !== '', function (Builder $query) use ($setting) {
                $minPoint = $setting->min_point;
                $targetPoint = $setting->target_point;

                if ($this->status === 'below_minimum') {
                    $query->where('current_point', '<', $minPoint);
                } elseif ($this->status === 'warning') {
                    $query->where('current_point', '>=', $minPoint)
                          ->where('current_point', '<', $targetPoint * 0.75);
                } elseif ($this->status === 'safe') {
                    $query->where('current_point', '>=', $targetPoint * 0.75);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::WaliKelas => Student::query()->whereIn(
                'classroom_id',
                $user->teacher?->homeroomClassrooms()->pluck('id') ?? collect(),
            ),
            UserRole::OrangTua => Student::query()->whereIn(
                'id',
                $user->parentGuardian?->students()->pluck('students.id') ?? collect(),
            ),
            default => Student::query(),
        };
    }
}; ?>

<div class="mx-auto max-w-6xl p-6">
    {{-- Header Tombol (Otomatis Hilang Saat Dicetak) --}}
    <div class="mb-6 flex items-center justify-between print:hidden">
        <a href="{{ route('attendance.points.monitoring') }}" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
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
            <h2 class="text-xl font-bold uppercase tracking-wide text-gray-900">Laporan Monitoring Poin Disiplin Siswa</h2>
            <p class="mt-1 text-sm text-gray-600">Sistem Monitoring & Pembinaan Siswa — SMARTSIS</p>
            <p class="mt-1 text-xs text-gray-500">Tanggal Cetak: {{ now()->translatedFormat('d F Y') }}</p>
        </div>

        <table class="w-full border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border border-gray-300 p-2 text-center w-12">No</th>
                    <th class="border border-gray-300 p-2 text-left">Nama Siswa</th>
                    <th class="border border-gray-300 p-2 text-left">NIS</th>
                    <th class="border border-gray-300 p-2 text-left">Kelas</th>
                    <th class="border border-gray-300 p-2 text-center">Poin</th>
                    <th class="border border-gray-300 p-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->students as $index => $student)
                    @php
                        $points = $student->current_point ?? 0;
                        $belowMin = $points < $this->setting->min_point;
                        $isSafe = $points >= $this->setting->target_point * 0.75;
                    @endphp
                    <tr>
                        <td class="border border-gray-300 p-2 text-center">{{ $index + 1 }}</td>
                        <td class="border border-gray-300 p-2 font-medium">{{ $student->name }}</td>
                        <td class="border border-gray-300 p-2">{{ $student->nis ?? '-' }}</td>
                        <td class="border border-gray-300 p-2">{{ $student->classroom?->name ?? '-' }}</td>
                        <td class="border border-gray-300 p-2 text-center font-semibold">
                            {{ $points }} / {{ $this->setting->target_point }}
                        </td>
                        <td class="border border-gray-300 p-2">
                            @if ($belowMin)
                                <span class="font-semibold text-red-600">Di Bawah Minimum</span>
                            @elseif ($isSafe)
                                <span class="font-semibold text-green-600">Aman</span>
                            @else
                                <span class="font-semibold text-amber-600">Peringatan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="border border-gray-300 p-4 text-center text-gray-500">
                            Tidak ada data siswa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>