@props([
    'data' => [], // array<int, array{label: string, value: int, color?: string}>
    'color' => 'bg-primary-500',
    'empty' => null,
])

@php
    $max = max(1, collect($data)->max('value') ?? 0);
@endphp

<div class="flex flex-col gap-2.5">
    @forelse ($data as $row)
        @php($pct = $row['value'] > 0 ? max(3, round($row['value'] / $max * 100)) : 0)
        <div class="flex items-center gap-3">
            <span class="w-28 shrink-0 truncate text-xs text-gray-500" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
            <div class="h-4 flex-1 overflow-hidden rounded-full bg-gray-100">
                <div @class(['h-full rounded-full', $row['color'] ?? $color]) style="width: {{ $pct }}%"></div>
            </div>
            <span class="w-8 shrink-0 text-right text-xs font-semibold tabular-nums text-gray-700">{{ number_format($row['value']) }}</span>
        </div>
    @empty
        <div class="flex h-16 items-center justify-center text-sm text-gray-400">
            {{ $empty ?? __('Belum ada data.') }}
        </div>
    @endforelse
</div>
