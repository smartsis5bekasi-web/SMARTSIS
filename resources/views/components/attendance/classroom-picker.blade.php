@props([
    'classrooms',
])

{{--
    Class filter for the face-scan attendance flow; binds to HandlesFaceScan's
    $classroomId. A native select is used on purpose: SlimSelect's wire:ignore
    wrapper would not re-sync after the scanner re-renders around it.
--}}
<label {{ $attributes->class('flex flex-col gap-1') }}>
    <span class="text-xs font-semibold text-gray-500">{{ __('Kelas yang discan') }}</span>
    <select wire:model.live="classroomId"
        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
        <option value="">{{ __('Semua kelas') }}</option>
        @foreach ($classrooms as $classroom)
            <option value="{{ $classroom->id }}" wire:key="kiosk-classroom-{{ $classroom->id }}">{{ $classroom->name }}</option>
        @endforeach
    </select>
</label>
