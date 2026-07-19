@props([
    'data' => [], // array<int, array{label: string, value: int, color: string}>
    'empty' => null,
])

@php
    $total = collect($data)->sum('value');
@endphp

<div class="w-full">
    @if ($total <= 0)
        <div class="flex h-8 items-center justify-center text-sm text-gray-400">
            {{ $empty ?? __('Belum ada data.') }}
        </div>
    @else
        <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full bg-gray-100">
            @foreach ($data as $segment)
                @continue($segment['value'] <= 0)
                @php($pct = round($segment['value'] / $total * 100, 1))
                <div @class(['h-full rounded-full', $segment['color']]) style="width: {{ $pct }}%"
                    title="{{ $segment['label'] }}: {{ number_format($segment['value']) }} ({{ $pct }}%)"></div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
            @foreach ($data as $segment)
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <span @class(['size-2 shrink-0 rounded-full', $segment['color']])></span>
                    {{ $segment['label'] }}
                    <span class="font-semibold tabular-nums text-gray-700">{{ number_format($segment['value']) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
