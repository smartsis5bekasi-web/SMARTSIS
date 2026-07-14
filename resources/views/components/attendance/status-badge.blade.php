@props([
    'status', // App\Enums\AttendanceStatus
])

{{-- Colored pill for a daily attendance status (hadir/terlambat/izin/sakit/alpha). --}}
<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-green-100 text-green-700' => $status === App\Enums\AttendanceStatus::Hadir,
    'bg-amber-100 text-amber-700' => $status === App\Enums\AttendanceStatus::Terlambat,
    'bg-blue-100 text-blue-700' => $status === App\Enums\AttendanceStatus::Izin,
    'bg-purple-100 text-purple-700' => $status === App\Enums\AttendanceStatus::Sakit,
    'bg-red-100 text-red-700' => $status === App\Enums\AttendanceStatus::Alpha,
])>{{ $status->label() }}</span>
