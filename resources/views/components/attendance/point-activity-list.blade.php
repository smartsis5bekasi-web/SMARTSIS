@props([
    'title',
    'logs',
    'showingAll' => false,
    'toggle' => null,
    'empty' => null,
])

{{--
    A "Penambahan"/"Pengurangan" panel for the student Development Point page.
    Lists point_logs with the rule name, note, date and signed delta, plus a
    "Lihat Semua" toggle wired to a Livewire boolean via $toggle().
--}}
<div class="rounded-xl border border-gray-100 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
        <p class="font-semibold text-gray-700">{{ $title }}</p>
        @if ($toggle && $logs->isNotEmpty())
            <button type="button" wire:click="{{ $toggle }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                {{ $showingAll ? __('Lihat Sedikit') : __('Lihat Semua') }}
            </button>
        @endif
    </div>

    <ul class="divide-y divide-gray-100">
        @forelse ($logs as $log)
            <li class="flex items-center justify-between gap-4 px-5 py-3">
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-800">{{ $log->pointRule?->name ?? $log->note ?? __('Penyesuaian') }}</p>
                    <p class="text-xs text-gray-400">{{ $log->created_at?->translatedFormat('d M Y') }}</p>
                </div>
                <span class="shrink-0 font-semibold tabular-nums {{ $log->delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $log->delta > 0 ? '+' : '' }}{{ $log->delta }}
                </span>
            </li>
        @empty
            <li class="px-5 py-4 text-center text-sm text-gray-400">{{ $empty }}</li>
        @endforelse
    </ul>
</div>
