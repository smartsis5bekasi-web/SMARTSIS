<div class="relative w-full overflow-x-auto">
    <table class="w-full text-left text-sm text-zinc-600">
        <thead class="border-b border-zinc-200 bg-zinc-50/50 text-xs font-medium uppercase text-zinc-500">
            <tr>
                <th scope="col" class="px-5 py-3.5">{{ __('Siswa') }}</th>
                <th scope="col" class="px-5 py-3.5">{{ __('Kelas') }}</th>
                <th scope="col" class="px-5 py-3.5 text-center">{{ __('Total Alpha') }}</th>
                <th scope="col" class="px-5 py-3.5 text-center">{{ __('Poin Disiplin') }}</th>
                <th scope="col" class="px-5 py-3.5">{{ __('Rekomendasi Tindakan') }}</th>
                <th scope="col" class="px-5 py-3.5 text-right">{{ __('Aksi') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white">
            @forelse ($warningStudents as $student)
                <tr class="hover:bg-zinc-50/50 transition-colors">
                    {{-- Nama & NIS --}}
                    <td class="px-5 py-4 font-medium text-zinc-900 whitespace-nowrap">
                        <div>{{ $student->name }}</div>
                        <div class="text-xs text-zinc-400 font-normal">NIS: {{ $student->nis }}</div>
                    </td>

                    {{-- Kelas --}}
                    <td class="px-5 py-4 whitespace-nowrap">
                        <flux:badge color="zinc" size="sm">
                            {{ $student->classroom?->name ?? '—' }}
                        </flux:badge>
                    </td>

                    {{-- Total Alpha --}}
                    <td class="px-5 py-4 text-center whitespace-nowrap">
                        @if ($student->alpha_count > 5)
                            <span class="inline-flex items-center font-semibold text-red-600">
                                {{ $student->alpha_count }} Hari
                            </span>
                        @else
                            <span class="text-zinc-500">{{ $student->alpha_count }} Hari</span>
                        @endif
                    </td>

                    {{-- Poin Disiplin --}}
                    <td class="px-5 py-4 text-center whitespace-nowrap">
                        <flux:badge size="md" :color="$student->current_point >= 75 ? 'green' : ($student->current_point >= 50 ? 'amber' : 'red')">
                            {{ $student->current_point }}
                        </flux:badge>
                    </td>

                    {{-- Rekomendasi Tindakan (Badges) --}}
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($student->recommendations as $rec)
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium border {{ $rec['color'] }}">
                                    {{ $rec['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </td>

                    {{-- Aksi --}}
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <flux:link :href="route('attendance.points.show', $student)" wire:navigate class="text-xs font-medium">
                            {{ __('Detail Siswa') }} →
                        </flux:link>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-zinc-400">
                        {{ __('Tidak ada siswa yang memerlukan tindakan bimbingan saat ini.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>