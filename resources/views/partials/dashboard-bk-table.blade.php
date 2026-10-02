@php($canRecordCounseling ??= false)

<div class="relative w-full overflow-x-auto">
    <table class="w-full text-left text-sm text-zinc-600">
        <thead class="border-b border-zinc-200 bg-zinc-50/50 text-xs font-medium uppercase text-zinc-500">
            <tr>
                <th scope="col" class="px-5 py-3.5">{{ __('Siswa') }}</th>
                <th scope="col" class="px-5 py-3.5">{{ __('Kelas') }}</th>
                <th scope="col" class="px-5 py-3.5 text-center">{{ __('Total Alpha') }}</th>
                <th scope="col" class="px-5 py-3.5 text-center">{{ __('Poin Disiplin') }}</th>
                <th scope="col" class="px-5 py-3.5">{{ __('Rekomendasi Tindakan') }}</th>
                <th scope="col" class="px-5 py-3.5">{{ __('Status Pemanggilan BK') }}</th>
                <th scope="col" class="px-5 py-3.5 text-right">{{ __('Aksi') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white">
            @forelse ($warningStudents as $student)
                @php($lastCounseling = $student->counselingRecords->first())
                <tr wire:key="bk-student-{{ $student->id }}" class="hover:bg-zinc-50/50 transition-colors">
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
                        @if ($student->alpha_count >= App\Enums\CounselingAction::ALPHA_THRESHOLD)
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
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium border {{ $rec['action']->badgeClasses() }}"
                                    @if ($rec['called']) title="{{ __('Sudah dipanggil BK') }}" @endif>
                                    @if ($rec['called'])
                                        <flux:icon.check class="size-3" />
                                    @endif
                                    {{ $rec['action']->label() }}
                                </span>
                            @endforeach
                        </div>
                    </td>

                    {{-- Status Pemanggilan BK --}}
                    <td class="px-5 py-4">
                        @if ($lastCounseling)
                            <div class="flex max-w-xs flex-col gap-1">
                                <span class="inline-flex w-fit items-center gap-1 rounded-md border border-green-200 bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                    <flux:icon.check-circle class="size-3.5" />
                                    {{ __('Sudah Dipanggil') }}
                                </span>
                                <span class="text-xs text-zinc-500">
                                    {{ $lastCounseling->action->label() }} · {{ $lastCounseling->called_on->translatedFormat('d M Y') }}
                                    @if ($student->counselingRecords->count() > 1)
                                        · {{ __(':count kali', ['count' => $student->counselingRecords->count()]) }}
                                    @endif
                                </span>
                                <span class="line-clamp-2 text-xs italic text-zinc-600" title="{{ $lastCounseling->note }}">“{{ $lastCounseling->note }}”</span>
                            </div>
                        @else
                            <span class="inline-flex items-center rounded-md border border-zinc-200 bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">
                                {{ __('Belum Dipanggil') }}
                            </span>
                        @endif
                    </td>

                    {{-- Aksi --}}
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <div class="flex flex-col items-end gap-2">
                            @if ($canRecordCounseling)
                                <button type="button" wire:click="openCounseling({{ $student->id }})"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700">
                                    <flux:icon.pencil-square class="size-4" />
                                    {{ $lastCounseling ? __('Catatan BK') : __('Catat Pemanggilan') }}
                                </button>
                            @endif
                            <flux:link :href="route('attendance.points.show', $student)" wire:navigate class="text-xs font-medium">
                                {{ __('Detail Siswa') }} →
                            </flux:link>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-8 text-center text-zinc-400">
                        {{ __('Tidak ada siswa yang memerlukan tindakan bimbingan saat ini.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
