@props([
    'result' => null,
])

{{-- Outcome card of the last face scan (HandlesFaceScan::$lastResult). --}}
<div {{ $attributes->class('rounded-xl bg-white p-6 drop-shadow-lg') }}>
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Hasil Terakhir') }}</p>

    @if ($result === null)
        <div class="flex flex-col items-center gap-2 py-10 text-gray-400">
            <ion-icon name="scan-outline" class="text-4xl"></ion-icon>
            <span class="text-sm">{{ __('Belum ada siswa terekam pada sesi ini.') }}</span>
        </div>
    @else
        <div class="mt-4 flex items-center gap-4">
            <img class="h-16 w-16 rounded-2xl object-cover"
                src="{{ $result['avatar'] ?? asset('assets/placeholder.png') }}"
                alt="{{ $result['name'] }}" />
            <div class="flex flex-col">
                <span class="font-bold text-gray-800">{{ $result['name'] }}</span>
                <span class="text-sm text-gray-500">{{ $result['classroom'] ?? '—' }}</span>
                <span class="text-sm text-gray-500">{{ $result['time'] }} WIB</span>
            </div>
        </div>

        <div @class([
            'mt-4 flex items-start gap-2 rounded-lg px-3 py-2.5 text-sm font-medium',
            'bg-green-50 text-green-700' => $result['ok'],
            'bg-red-50 text-red-700' => ! $result['ok'],
        ])>
            <ion-icon name="{{ $result['ok'] ? 'checkmark-circle-outline' : 'alert-circle-outline' }}" class="mt-0.5 shrink-0 text-lg"></ion-icon>
            <span>{{ $result['message'] }}</span>
        </div>
    @endif
</div>
