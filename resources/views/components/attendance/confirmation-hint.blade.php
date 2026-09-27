@props([
    'attendance', // App\Models\Attendance
    'setting' => null, // App\Models\AttendanceSetting — list pages pass it to skip a lookup per row
])

{{-- The warning that goes with a day nobody explained: by when a pending day
     turns into Alpha on its own, or that it already did. An automatic Alpha
     stays amber until a teacher checks it; changing its status gives the
     points back. --}}
@php($setting ??= App\Models\AttendanceSetting::current())

@if ($attendance->status->isPending())
    @php($deadline = $setting->pendingDeadline($attendance->date))
    @if ($deadline !== null)
        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700"
            title="{{ __('Jika belum dikonfirmasi sampai akhir :date, otomatis menjadi Alpha dan poin dikurangi.', ['date' => $deadline->translatedFormat('l, d M Y')]) }}">
            <ion-icon name="alarm-outline" class="text-sm"></ion-icon>
            @if ($deadline->lt(today()))
                {{ __('Lewat batas — segera jadi Alpha') }}
            @else
                {{ __('Batas konfirmasi :date', ['date' => $deadline->translatedFormat('D, d M')]) }}
            @endif
        </span>
    @endif
@elseif ($attendance->isEscalated())
    <span @class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
        'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-300' => ! $attendance->isVerified(),
        'bg-gray-100 text-gray-600' => $attendance->isVerified(),
    ])
        title="{{ __('Tidak dikonfirmasi guru dalam batas waktu, jadi sistem menandai Alpha pada :time. Status masih bisa diubah — poin dikembalikan otomatis.', ['time' => $attendance->escalated_at->translatedFormat('d M Y H:i')]) }}">
        <ion-icon name="flash-outline" class="text-sm"></ion-icon>
        {{ $attendance->isVerified() ? __('Alpha otomatis · sudah dicek') : __('Alpha otomatis · belum dicek') }}
    </span>
@endif
