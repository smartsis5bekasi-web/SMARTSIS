@props([
    'level', // App\Enums\WarningLevel
])

{{-- Colored pill for a warning letter level (SP1 → SP3 by severity). --}}
<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-amber-100 text-amber-700' => $level === App\Enums\WarningLevel::Sp1,
    'bg-orange-100 text-orange-700' => $level === App\Enums\WarningLevel::Sp2,
    'bg-red-100 text-red-700' => $level === App\Enums\WarningLevel::Sp3,
])>{{ $level->label() }}</span>
