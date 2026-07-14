<?php

use App\Enums\UserRole;
use App\Models\WarningLetter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::print')] #[Title('Cetak Surat Peringatan')] class extends Component {
    public WarningLetter $warningLetter;

    public function mount(WarningLetter $warningLetter): void
    {
        // Only issued letters have a printable document (F-23).
        abort_unless($warningLetter->isIssued(), 404);
        abort_unless($this->canView($warningLetter), 403);

        $this->warningLetter = $warningLetter->load(['student.classroom', 'decider']);
    }

    private function canView(WarningLetter $letter): bool
    {
        $user = auth()->user();

        return match ($user->primaryRole()) {
            UserRole::Siswa => $user->student?->id === $letter->student_id,
            UserRole::OrangTua => (bool) $user->parentGuardian?->students()->whereKey($letter->student_id)->exists(),
            UserRole::WaliKelas => $letter->student?->classroom_id !== null
                && (bool) $user->teacher?->homeroomClassrooms()->whereKey($letter->student->classroom_id)->exists(),
            default => true,
        };
    }
}; ?>

<div class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-8 print:max-w-none print:p-0">
    {{-- Toolbar (hidden on paper). --}}
    <div class="flex items-center justify-between print:hidden">
        <a href="{{ route('warnings.show', $warningLetter) }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-700">
            <ion-icon name="arrow-back-outline" class="text-lg"></ion-icon> {{ __('Kembali') }}
        </a>
        <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">
            <ion-icon name="print-outline" class="text-lg"></ion-icon> {{ __('Cetak / Simpan PDF') }}
        </button>
    </div>

    {{-- The letter. --}}
    <div class="rounded-xl bg-white p-10 shadow-sm print:rounded-none print:p-0 print:shadow-none">
        {{-- Kop surat --}}
        <div class="flex items-center gap-4 border-b-4 border-double border-gray-800 pb-4">
            <x-app-logo-icon class="h-16 w-16 fill-current text-primary-700" />
            <div class="flex-1 text-center">
                <p class="text-lg font-bold uppercase tracking-wide text-gray-900">Pemerintah Daerah Provinsi Jawa Barat</p>
                <p class="text-xl font-bold uppercase tracking-wide text-gray-900">SMA Negeri 5 Bekasi</p>
                <p class="text-xs text-gray-600">{{ __('Sistem Monitoring & Pembinaan Siswa — SMARTSIS') }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-1 text-sm text-gray-800">
            <div class="flex gap-2"><span class="w-24 shrink-0">{{ __('Nomor') }}</span><span>: {{ $warningLetter->letter_number }}</span></div>
            <div class="flex gap-2"><span class="w-24 shrink-0">{{ __('Lampiran') }}</span><span>: —</span></div>
            <div class="flex gap-2"><span class="w-24 shrink-0">{{ __('Perihal') }}</span><span>: <strong>{{ $warningLetter->level->letterTitle() }}</strong></span></div>
        </div>

        <div class="mt-6 text-sm leading-relaxed text-gray-800">
            <p>{{ __('Kepada Yth.') }}<br>
                {{ __('Orang Tua/Wali dari') }} <strong>{{ $warningLetter->student?->name }}</strong><br>
                {{ __('Kelas :classroom', ['classroom' => $warningLetter->student?->classroom?->name ?? '—']) }}<br>
                {{ __('di tempat') }}</p>

            <p class="mt-4">{{ __('Dengan hormat,') }}</p>

            <p class="mt-2 text-justify indent-8">
                {{ __('Berdasarkan pemantauan sistem kredit poin kedisiplinan SMARTSIS, poin kedisiplinan ananda tercatat sebesar :points poin, telah mencapai ambang penerbitan :title (poin ≤ :threshold). Sehubungan dengan hal tersebut, sekolah menerbitkan :title bagi ananda sebagai bentuk pembinaan.', [
                    'points' => $warningLetter->points_snapshot,
                    'title' => $warningLetter->level->letterTitle(),
                    'threshold' => $warningLetter->threshold,
                ]) }}
            </p>

            <p class="mt-2 text-justify indent-8">
                {{ __('Kami mengharapkan perhatian serta kerja sama Bapak/Ibu dalam membimbing ananda agar dapat memperbaiki kedisiplinannya di sekolah. Silakan menghubungi Guru Bimbingan Konseling untuk informasi dan langkah pembinaan selanjutnya.') }}
            </p>

            <p class="mt-2 text-justify indent-8">
                {{ __('Demikian surat ini disampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.') }}
            </p>
        </div>

        <div class="mt-10 flex justify-between text-sm text-gray-800">
            <div class="flex flex-col gap-16 text-center">
                <span>{{ __('Mengetahui,') }}<br>{{ __('Kepala Sekolah') }}</span>
                <span class="font-semibold underline">(..................................)</span>
            </div>
            <div class="flex flex-col gap-16 text-center">
                <span>
                    {{ __('Bekasi, :date', ['date' => $warningLetter->decided_at?->translatedFormat('d F Y')]) }}<br>
                    {{ __('Guru Bimbingan Konseling') }}
                </span>
                <span class="font-semibold underline">{{ $warningLetter->decider?->name ?? '(..................................)' }}</span>
            </div>
        </div>
    </div>
</div>
