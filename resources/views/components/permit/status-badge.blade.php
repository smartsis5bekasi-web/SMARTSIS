@props([
    'status', // App\Enums\PermitStatus
])

{{-- Colored pill for a permit status (menunggu/disetujui/ditolak). --}}
<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-amber-100 text-amber-700' => $status === App\Enums\PermitStatus::Pending,
    'bg-green-100 text-green-700' => $status === App\Enums\PermitStatus::Approved,
    'bg-red-100 text-red-700' => $status === App\Enums\PermitStatus::Rejected,
])>{{ $status->label() }}</span>
