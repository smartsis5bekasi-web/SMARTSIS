@props([
    'setting',
])

{{-- Today's absensi windows, from AttendanceSetting::current(). --}}
<div {{ $attributes->class('rounded-xl bg-white p-6 drop-shadow-lg') }}>
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Jadwal Absensi') }}</p>
    <dl class="mt-4 flex flex-col gap-3 text-sm">
        <div class="flex items-center justify-between">
            <dt class="text-gray-500">{{ __('Absensi masuk mulai') }}</dt>
            <dd class="font-semibold text-gray-800">{{ substr($setting->check_in_start, 0, 5) }}</dd>
        </div>
        <div class="flex items-center justify-between">
            <dt class="text-gray-500">{{ __('Terlambat setelah') }}</dt>
            <dd class="font-semibold text-gray-800">{{ substr($setting->late_after, 0, 5) }}</dd>
        </div>
        <div class="flex items-center justify-between">
            <dt class="text-gray-500">{{ __('Absensi pulang mulai') }}</dt>
            <dd class="font-semibold text-gray-800">{{ substr($setting->check_out_after, 0, 5) }}</dd>
        </div>
    </dl>
    {{ $slot }}
</div>
