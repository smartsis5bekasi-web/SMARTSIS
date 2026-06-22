@props([
    'students',
    'empty' => 'Belum ada data.',
])

@if ($students->isEmpty())
    <div class="p-6">
        <flux:text>{{ $empty }}</flux:text>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-zinc-200 text-zinc-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Nama') }}</th>
                    <th class="px-5 py-3 text-start font-medium">NIS</th>
                    <th class="px-5 py-3 text-start font-medium">{{ __('Kelas') }}</th>
                    <th class="px-5 py-3 text-end font-medium">{{ __('Poin') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($students as $student)
                    <tr>
                        <td class="px-5 py-3 font-medium text-zinc-800">{{ $student->name }}</td>
                        <td class="px-5 py-3 text-zinc-600">{{ $student->nis }}</td>
                        <td class="px-5 py-3 text-zinc-600">{{ $student->classroom?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-end">
                            <flux:badge size="sm" :color="$student->current_point >= 75 ? 'green' : ($student->current_point >= 50 ? 'amber' : 'red')">
                                {{ $student->current_point }}
                            </flux:badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
