@props([
    'attendance', // App\Models\Attendance
])

{{-- Whether the record has been confirmed. Absensi does not match faces, so
     the GPS fix is what verifies a student's own scan; a scan without a
     location, and every self-declared sakit/izin, waits for a Guru Piket /
     Wali Kelas instead. --}}
@if ($attendance->isVerified())
    <span class="inline-flex items-center gap-1 text-green-600" title="{{ __('Terverifikasi :time', ['time' => $attendance->verified_at->translatedFormat('d M Y H:i')]) }}">
        <ion-icon name="checkmark-circle" class="text-xl"></ion-icon>
    </span>
@else
    <span class="inline-flex items-center gap-1 text-red-500"
        title="{{ $attendance->hasLocation() ? __('Menunggu verifikasi petugas') : __('Menunggu verifikasi — lokasi tidak terekam') }}">
        <ion-icon name="close-circle" class="text-xl"></ion-icon>
    </span>
@endif
