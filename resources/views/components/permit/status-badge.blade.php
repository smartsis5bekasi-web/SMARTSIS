@props([
    'permit', // App\Models\Permit
])

{{-- Colored pill for a permit's combined Guru Piket + Wali Kelas approval state. --}}
<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-amber-100 text-amber-700' => $permit->isPending(),
    'bg-sky-100 text-sky-700' => $permit->status === App\Enums\PermitStatus::Approved && ! $permit->isHomeroomApproved(),
    'bg-green-100 text-green-700' => $permit->isFullyApproved(),
    'bg-red-100 text-red-700' => $permit->status === App\Enums\PermitStatus::Rejected,
])>{{ $permit->statusLabel() }}</span>
