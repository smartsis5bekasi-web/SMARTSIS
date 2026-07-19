<?php

use App\Exports\Sheets\StudentTemplateSheet;
use App\Exports\StudentImportTemplate;
use App\Exports\StudentsExport;
use App\Livewire\Concerns\ImportsSpreadsheetRows;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\ParentGuardian;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Siswa')] class extends Component {
    use ImportsSpreadsheetRows;
    use WithFileUploads;
    use WithPagination;

    public bool $showImportModal = false;

    public ?TemporaryUploadedFile $importFile = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->with(['classroom', 'major', 'teacher', 'parents'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Student $student): void
    {
        $student->delete();

        $this->dispatch('swal', icon: 'success', title: __('Siswa dihapus.'));
    }

    public function openImportModal(): void
    {
        $this->reset('importFile', 'importErrors');
        $this->resetValidation();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->reset('importFile', 'importErrors', 'showImportModal');
        $this->resetValidation();
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new StudentsExport, 'data-siswa-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Download the .xlsx import template with sample rows plus a reference
     * sheet for the kelas/jurusan/hubungan relation columns.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new StudentImportTemplate, 'template-import-siswa.xlsx');
    }

    public function import(): void
    {
        $this->importErrors = [];

        $this->validate(
            ['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']],
            [
                'importFile.required' => __('Pilih file terlebih dahulu.'),
                'importFile.mimes' => __('File harus berformat Excel (.xlsx) atau CSV.'),
                'importFile.max' => __('Ukuran file maksimal 5MB.'),
            ],
        );

        [$rows, $formatError] = $this->parseImportFile(['nama', 'nis', 'kelas']);

        if ($formatError !== null) {
            $this->importErrors = [$formatError];

            return;
        }

        if ($rows === []) {
            $this->importErrors = [__('File tidak berisi data siswa.')];

            return;
        }

        $classrooms = Classroom::query()->get()->mapWithKeys(fn (Classroom $classroom) => [mb_strtolower($classroom->name) => $classroom->id]);
        $majors = Major::query()->get()->mapWithKeys(fn (Major $major) => [mb_strtolower($major->name) => $major->id]);

        $prepared = [];
        $errors = [];
        $seenNis = [];
        $seenNisn = [];

        foreach ($rows as $line => $row) {
            $validator = Validator::make($row, [
                'nama' => ['required', 'string', 'max:100'],
                'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')],
                'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')],
                'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
                'alamat' => ['nullable', 'string', 'max:255'],
                'kelas' => ['required'],
                'orang_tua' => ['nullable', 'string', 'max:100'],
                'telepon_orang_tua' => ['nullable', 'string', 'max:20'],
            ], [
                'nama.required' => __('kolom nama wajib diisi'),
                'nama.max' => __('nama maksimal 100 karakter'),
                'nis.required' => __('kolom nis wajib diisi'),
                'nis.max' => __('NIS maksimal 30 karakter'),
                'nis.unique' => __('NIS sudah terdaftar'),
                'nisn.max' => __('NISN maksimal 30 karakter'),
                'nisn.unique' => __('NISN sudah terdaftar'),
                'tanggal_lahir.date_format' => __('format tanggal lahir harus YYYY-MM-DD'),
                'alamat.max' => __('alamat maksimal 255 karakter'),
                'kelas.required' => __('kolom kelas wajib diisi'),
                'orang_tua.max' => __('nama orang tua maksimal 100 karakter'),
                'telepon_orang_tua.max' => __('telepon orang tua maksimal 20 karakter'),
            ]);

            $rowErrors = $validator->errors()->all();

            $classroomId = isset($row['kelas']) ? $classrooms->get(mb_strtolower($row['kelas'])) : null;

            if (isset($row['kelas']) && $classroomId === null) {
                $rowErrors[] = __('kelas ":value" tidak ditemukan, lihat sheet Referensi pada template', ['value' => $row['kelas']]);
            }

            $majorId = null;

            if (isset($row['jurusan'])) {
                $majorId = $majors->get(mb_strtolower($row['jurusan']));

                if ($majorId === null) {
                    $rowErrors[] = __('jurusan ":value" tidak ditemukan, lihat sheet Referensi pada template', ['value' => $row['jurusan']]);
                }
            }

            $gender = null;

            if (isset($row['jenis_kelamin'])) {
                $gender = match (mb_strtolower($row['jenis_kelamin'])) {
                    'l', 'laki-laki' => 'L',
                    'p', 'perempuan' => 'P',
                    default => null,
                };

                if ($gender === null) {
                    $rowErrors[] = __('jenis kelamin ":value" tidak dikenali, gunakan L atau P', ['value' => $row['jenis_kelamin']]);
                }
            }

            $relationship = null;

            if (isset($row['orang_tua'])) {
                $relationship = match (mb_strtolower($row['hubungan'] ?? 'wali')) {
                    'ayah' => 'Ayah',
                    'ibu' => 'Ibu',
                    'wali' => 'Wali',
                    default => null,
                };

                if ($relationship === null) {
                    $rowErrors[] = __('hubungan ":value" tidak dikenali, gunakan Ayah/Ibu/Wali', ['value' => $row['hubungan']]);
                }
            }

            if (isset($row['nis'], $seenNis[$row['nis']])) {
                $rowErrors[] = __('NIS duplikat dengan baris :line', ['line' => $seenNis[$row['nis']]]);
            }

            if (isset($row['nisn'], $seenNisn[$row['nisn']])) {
                $rowErrors[] = __('NISN duplikat dengan baris :line', ['line' => $seenNisn[$row['nisn']]]);
            }

            if ($rowErrors !== []) {
                $errors[] = __('Baris :line: :messages', ['line' => $line, 'messages' => implode('; ', $rowErrors)]);

                continue;
            }

            if (isset($row['nis'])) {
                $seenNis[$row['nis']] = $line;
            }

            if (isset($row['nisn'])) {
                $seenNisn[$row['nisn']] = $line;
            }

            $prepared[] = [
                'name' => $row['nama'],
                'nis' => $row['nis'],
                'nisn' => $row['nisn'] ?? null,
                'gender' => $gender,
                'birth_date' => $row['tanggal_lahir'] ?? null,
                'address' => $row['alamat'] ?? null,
                'classroom_id' => $classroomId,
                'major_id' => $majorId,
                'parent' => isset($row['orang_tua']) ? [
                    'name' => $row['orang_tua'],
                    'relationship' => $relationship,
                    'phone' => $row['telepon_orang_tua'] ?? null,
                ] : null,
            ];
        }

        if ($errors !== []) {
            $this->importErrors = $errors;

            return;
        }

        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as $row) {
                $parentRow = $row['parent'];
                unset($row['parent']);

                $student = Student::create($row);

                if ($parentRow !== null) {
                    $parent = ParentGuardian::create([
                        'name' => $parentRow['name'],
                        'phone' => $parentRow['phone'],
                    ]);

                    $student->parents()->attach($parent->id, ['relationship' => $parentRow['relationship']]);
                }
            }
        });

        $this->closeImportModal();
        $this->resetPage();

        $this->dispatch('swal', icon: 'success', title: __(':count siswa berhasil diimpor.', ['count' => count($prepared)]));
    }

    /**
     * Template header + sample rows shown in the modal, sourced from the
     * downloadable template so they never drift apart.
     *
     * @return array<int, array<int, string>>
     */
    public function sampleRows(): array
    {
        $sheet = new StudentTemplateSheet;

        return [$sheet->headings(), ...$sheet->array()];
    }
}; ?>

<div class="justify-center max-xl:w-full">
    <x-ui.page-header :title="__('Siswa')" :subtitle="__('Kelola data siswa.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download-outline" wire:click="export">
                {{ __('Export') }}
            </x-ui.button>
            <x-ui.button variant="secondary" icon="cloud-upload-outline" wire:click="openImportModal">
                {{ __('Import') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.students.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
     <div class="flex-col bg-white rounded-xl p-8 drop-shadow-lg mt-5">
           <div class="rounded-2xl border bg-gray-100 overflow-auto">
            <table class="border-collapse rounded-2xl min-w-full leading-normal">
                <thead class="rounded-2xl">
                    <tr class="rounded-2xl text-gray-500 font-normal text-md text-left whitespace-nowrap">
                        <th class="py-2.5 px-4">No</th>
                        <th class="py-2.5 px-4">Nama Siswa</th>
                        <th class="py-2.5 px-4">Email Siswa</th>
                        <th class="py-2.5 px-4">Kelas</th>
                        <th class="py-2.5 px-4">Jurusan</th>
                        <th class="py-2.5 px-4">Jenis Kelamin</th>
                        <th class="py-2.5 px-4">Orang Tua</th>
                            <th class="py-2.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="rounded-2xl bg-white text-gray-700">
                     @forelse ($this->students as $key => $student)
                        <tr wire:key="{{ $student->id }}">
                            <td class="py-2.5 px-4">

                                {{ $key + $this->students->firstItem() }}
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="flex flex-col items-left gap-6 px-2">
                                    <img class="w-16 object-fill rounded-2xl" src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                                    <p class="max-w-xs">{{ $student->name }}</p>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-left">
                              <p class="max-w-xs">{{ $student->user->email ?? '-' }}</p>
                            </td>
                            <td class="py-2.5 px-4">
                                {{ $student->classroom?->name ?? '—' }}
                            </td>
                            <td class="py-2.5 px-4">
                                {{ $student->major?->name ?? '-' }}
                            </td>
                            <td class="py-2.5 px-4">
                              {{ $student->gender === 'L' ? __('Laki-laki') : ($student->gender === 'P' ? __('Perempuan') : '—') }}
                            </td>
                            <td class="py-2.5 px-4">
                                @forelse ($student->parents as $parent)
                                    <p class="max-w-xs whitespace-nowrap">{{ $parent->name }} <span class="text-xs text-gray-400">({{ $parent->pivot->relationship ?? '—' }})</span></p>
                                @empty
                                    —
                                @endforelse
                            </td>

                                <td class="py-2.5 px-4">
                                    <div class="flex flex-col items-center gap-y-2">
                                    <div class="flex flex-row items-center gap-x-4">
                                          <a href="#" title="Lihat" target="_blank"  title="Lihat"><ion-icon name="eye-outline" class="text-2xl text-primary"></ion-icon></a>
                                        {{-- <a href="{{ route('umkm.detail', [$mentor->hasCollaborator->slug, 'type' => 'mentors']) }}" title="Lihat" target="_blank"  title="Lihat"><ion-icon name="eye-outline" class="text-2xl text-primary"></ion-icon></a> --}}
                                        <a href="#" class="text-primary" title="Ubah">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M8 3H3C1.89543 3 1 3.89543 1 5V16C1 17.1046 1.89543 18 3 18H14C15.1046 18 16 17.1046 16 16V11M14.5858 1.58579C15.3668 0.804738 16.6332 0.804738 17.4142 1.58579C18.1953 2.36683 18.1953 3.63316 17.4142 4.41421L8.82842 13H6L6 10.1716L14.5858 1.58579Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                        <form method="POST" class="m-0 flex items-center form-delete-mentor" action="#">
                                            <button type="submit" class="text-primary" title="Hapus">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M16 5L15.1327 17.1425C15.0579 18.1891 14.187 19 13.1378 19H4.86224C3.81296 19 2.94208 18.1891 2.86732 17.1425L2 5M7 9V15M11 9V15M12 5V2C12 1.44772 11.5523 1 11 1H7C6.44772 1 6 1.44772 6 2V5M1 5H17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                    </div>
                                </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-5 text-center">Belum ada data Mentor yang dapat ditampilkan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
           </div>
     </div>

    @if ($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeImportModal()">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeImportModal"></div>

            <div class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Import Siswa') }}</h2>
                    <button type="button" wire:click="closeImportModal" class="inline-flex text-gray-400 transition hover:text-gray-600" title="{{ __('Tutup') }}">
                        <ion-icon name="close-outline" class="text-2xl"></ion-icon>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    <p class="text-sm text-gray-600">
                        {{ __('Sebelum upload, unduh template Excel (.xlsx) terlebih dahulu') }}
                        <button type="button" wire:click="downloadTemplate" class="font-semibold text-primary-600 underline transition hover:text-primary-700">
                            {{ __('klik di sini') }}
                        </button>
                        {{ __('lalu isi datanya sesuai contoh di bawah. Daftar kelas & jurusan yang tersedia ada di sheet "Referensi" pada template.') }}
                    </p>

                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                        <table class="min-w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-left text-gray-500">
                                    @foreach ($this->sampleRows()[0] as $column)
                                        <th class="whitespace-nowrap px-3 py-2 font-medium">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                @foreach (array_slice($this->sampleRows(), 1) as $row)
                                    <tr wire:key="sample-row-{{ $loop->index }}">
                                        @foreach ($row as $cell)
                                            <td class="whitespace-nowrap px-3 py-2">{{ $cell !== '' ? $cell : '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg bg-primary-50 px-4 py-3 text-xs text-primary-800">
                        <p class="mb-1.5 font-semibold">{{ __('Nilai untuk kolom relasi & pilihan:') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 font-medium text-primary-700">{{ __('kelas: sesuai nama kelas terdaftar') }}</span>
                            <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 font-medium text-primary-700">{{ __('jurusan: sesuai nama jurusan terdaftar') }}</span>
                            <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 font-medium text-primary-700">{{ __('jenis_kelamin: L / P') }}</span>
                            <span class="inline-flex rounded-full bg-white px-2.5 py-0.5 font-medium text-primary-700">{{ __('hubungan: Ayah / Ibu / Wali') }}</span>
                        </div>
                    </div>

                    <ul class="list-inside list-disc space-y-1 text-xs text-gray-500">
                        <li>{{ __('Kolom nama, nis, dan kelas wajib diisi; NIS & NISN tidak boleh sama dengan data yang sudah ada.') }}</li>
                        <li>{{ __('Kolom tanggal_lahir menggunakan format YYYY-MM-DD, contoh 2008-03-15.') }}</li>
                        <li>{{ __('Kolom orang_tua opsional — jika diisi, data orang tua/wali otomatis dibuat dan dihubungkan.') }}</li>
                    </ul>

                    <div>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt"
                            class="block w-full cursor-pointer rounded-md border border-gray-200 text-sm text-gray-600 file:mr-3 file:cursor-pointer file:rounded-l-md file:border-0 file:bg-primary-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                        <p wire:loading wire:target="importFile" class="mt-1 text-xs text-gray-500">{{ __('Mengunggah file...') }}</p>
                        @error('importFile')
                            <span class="mt-1 block text-sm text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    @if ($importErrors !== [])
                        <div class="max-h-40 overflow-y-auto rounded-lg bg-red-50 px-4 py-3 text-xs text-red-700">
                            <p class="mb-1.5 font-semibold">{{ __('Import dibatalkan, perbaiki data berikut:') }}</p>
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($importErrors as $error)
                                    <li wire:key="import-error-{{ $loop->index }}">{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <x-ui.button variant="secondary" wire:click="closeImportModal">
                        {{ __('Batal') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="cloud-upload-outline" wire:click="import" wire:loading.attr="disabled" wire:target="import, importFile">
                        <span wire:loading.remove wire:target="import">{{ __('Upload') }}</span>
                        <span wire:loading wire:target="import">{{ __('Memproses...') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
