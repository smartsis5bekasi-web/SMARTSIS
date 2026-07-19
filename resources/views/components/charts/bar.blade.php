@props([
    'data' => [], // array<int, array{label: string, value: int|float, color?: string}>
    'color' => 'bg-primary-500',
    'height' => 140,
    'labelEvery' => 1,
    'empty' => null,
])

@php
    $max = max(1, collect($data)->max('value') ?? 0);
    $peakIndex = collect($data)->pluck('value')->search($max, true);
    $lastIndex = count($data) - 1;
@endphp

<div class="w-full">
    @if (count($data) === 0)
        <div class="flex items-center justify-center text-sm text-gray-400" style="height: {{ $height }}px">
            {{ $empty ?? __('Belum ada data.') }}
        </div>
    @else
        <div class="flex items-end gap-1 border-b border-gray-100" style="height: {{ $height }}px">
            @foreach ($data as $i => $point)
                @php
                    $value = $point['value'];
                    $pct = $value > 0 ? max(3, round($value / $max * 100)) : 0;
                @endphp
                <div class="group relative flex h-full flex-1 flex-col items-center justify-end"
                    title="{{ $point['label'] }}: {{ number_format($value) }}">
                    @if ($i === $peakIndex && $value > 0)
                        <span class="mb-1 text-[10px] font-semibold tabular-nums text-gray-500">{{ number_format($value) }}</span>
                    @endif
                    <div @class([
                        'w-full max-w-6 rounded-t transition-opacity group-hover:opacity-75',
                        $point['color'] ?? $color,
                    ]) style="height: {{ $pct }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-1">
            @foreach ($data as $i => $point)
                <div class="flex-1 truncate text-center text-[10px] text-gray-400">
                    {{ $i % $labelEvery === 0 || $i === $lastIndex ? $point['label'] : '' }}
                </div>
            @endforeach
        </div>
    @endif
</div>
