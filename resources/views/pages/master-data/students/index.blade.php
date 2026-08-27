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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Livewire\Concerns\TogglesUserActiveStatus; 
use App\Enums\UserRole;
use App\Models\User;

new #[Title('Siswa')] class extends Component {
    use ImportsSpreadsheetRows;
    use TogglesUserActiveStatus;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $classroomId = '';

    public string $majorId = '';

    public string $gender = '';

    public string $status = '';

    public bool $showImportModal = false;

    public ?TemporaryUploadedFile $importFile = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassroomId(): void
    {
        $this->resetPage();
    }

    public function updatedMajorId(): void
    {
        $this->resetPage();
    }

    public function updatedGender(): void 
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'classroomId', 'majorId', 'gender', 'status']);
        $this->resetPage();
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]

    public function classrooms(): Collection
    {
        return Classroom::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Major>
     */
    #[Computed]
    public function majors(): Collection
    {
        return Major::query()->orderBy('name')->get();
    }

   /**
     * @return LengthAwarePaginator<int, Student>
     */
   #[Computed]
    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->with(['classroom', 'major', 'teacher', 'parents', 'user'])
            ->when(filled($this->search), function (Builder $query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('nis', 'like', $term)
                        ->orWhere('nisn', 'like', $term)
                        ->orWhereHas('user', fn (Builder $uq) => $uq->where('email', 'like', $term));
                });
            })
            ->when(filled($this->classroomId), fn (Builder $query) => $query->where('classroom_id', $this->classroomId))
            ->when(filled($this->majorId), fn (Builder $query) => $query->where('major_id', $this->majorId))
            ->when(filled($this->gender), fn (Builder $query) => $query->where('gender', $this->gender))
            ->when($this->status !== '', function (Builder $query) {
                $isActive = $this->status === 'active';
                $query->whereHas('user', fn (Builder $uq) => $uq->where('is_active', $isActive)); })
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Student $student): void
    {
        $student->loadMissing('user');
        $student->user?->delete();
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

    [$rows, $formatError] = $this->parseImportFile(['nama', 'nis', 'email', 'kelas']);

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
    $seenEmails = [];

    foreach ($rows as $line => $row) {
        $validator = Validator::make($row, [
            'nama' => ['required', 'string', 'max:100'],
            'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')],
            'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8'],
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
            'email.required' => __('kolom email wajib diisi'),
            'email.email' => __('format email tidak valid'),
            'email.unique' => __('email sudah terdaftar'),
            'password.min' => __('password minimal 8 karakter'),
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

        $email = mb_strtolower((string) ($row['email'] ?? ''));

        if ($email !== '' && isset($seenEmails[$email])) {
            $rowErrors[] = __('email duplikat dengan baris :line', ['line' => $seenEmails[$email]]);
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

        $seenEmails[$email] = $line;

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
            'email' => $row['email'],
            'password' => $row['password'] ?? 'password',
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
            $email = $row['email'];
            $password = $row['password'];
            unset($row['parent'], $row['email'], $row['password']);

            $user = User::create([
                'name' => $row['name'],
                'email' => $email,
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole(UserRole::Siswa->value);

            $student = Student::create([...$row, 'user_id' => $user->id]);

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

<div class="flex h-full w-full flex-1 flex-col gap-6">
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
    
<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <div class="overflow-x-auto">
        {{-- Section Search & Filter --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                {{-- Search Input --}}
                <div class="relative min-w-[240px] flex-1 sm:flex-none">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="{{ __('Cari nama, NIS, NISN, email...') }}"
                        class="w-full rounded-md border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <ion-icon name="search-outline" class="text-gray-400"></ion-icon>
                    </div>
                </div>

                {{-- Filter Kelas --}}
                <select wire:model.live="classroomId"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Kelas') }}</option>
                    @foreach ($this->classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>

                {{-- Filter Jurusan --}}
                <select wire:model.live="majorId"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Jurusan') }}</option>
                    @foreach ($this->majors as $major)
                        <option value="{{ $major->id }}">{{ $major->name }}</option>
                    @endforeach
                </select>

                {{-- Filter Jenis Kelamin --}}
                <select wire:model.live="gender"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Gender') }}</option>
                    <option value="L">{{ __('Laki-laki') }}</option>
                    <option value="P">{{ __('Perempuan') }}</option>
                </select>

                {{-- Filter Status Akun --}}
                <select wire:model.live="status"
                    class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="active">{{ __('Aktif') }}</option>
                    <option value="inactive">{{ __('Nonaktif') }}</option>
                </select>

                @if ($search !== '' || $classroomId !== '' || $majorId !== '' || $gender !== '' || $status !== '')
                    <button type="button" wire:click="resetFilters" class="text-xs font-medium text-red-600 hover:underline">
                        {{ __('Reset Filter') }}
                    </button>
                @endif
            </div>
        </div>
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-gray-500">
                    <th class="px-4 py-3 font-medium">No</th>
                    <th class="px-4 py-3 font-medium">Foto</th>
                    <th class="px-4 py-3 font-medium">Nama Siswa</th>
                    <th class="px-4 py-3 font-medium">Email Siswa</th>
                    <th class="px-4 py-3 font-medium">Kelas</th>
                    <th class="px-4 py-3 font-medium">Jurusan</th>
                    <th class="px-4 py-3 font-medium">Jenis Kelamin</th>
                    <th class="px-4 py-3 font-medium">Orang Tua</th>
                    <th class="px-4 py-3 text-right font-medium">Aksi</th>
                    <th class="px-4 py-3 text-right font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                @forelse ($this->students as $key => $student)
                    <tr wire:key="{{ $student->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $key + $this->students->firstItem() }}</td>
                        <td class="px-4 py-3">
                            <img class="h-10 w-10 rounded-full border border-gray-200 object-cover shadow-sm sm:h-12 sm:w-12"
                                src="{{ $student->avatar_url ?? asset('assets/placeholder.png') }}" alt="{{ $student->name }}" />
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $student->name }}</td>
                        <td class="px-4 py-3">{{ $student->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $student->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $student->major?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ $student->gender === 'L' ? __('Laki-laki') : ($student->gender === 'P' ? __('Perempuan') : '—') }}
                        </td>
                        <td class="px-4 py-3">
                            @forelse ($student->parents as $parent)
                                <p class="whitespace-nowrap">{{ $parent->name }} <span class="text-xs text-gray-400">({{ $parent->pivot->relationship ?? '—' }})</span></p>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('master-data.students.show', $student) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Lihat') }}">
                                    <ion-icon name="eye-outline" class="text-xl"></ion-icon>
                                </a>
                                <a href="{{ route('master-data.students.edit', $student) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Ubah') }}">
                                    <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                </a>
                                <x-ui.delete-button :wire-id="$student->id" :text="__('Akun login terkait juga akan dihapus dan tidak dapat dikembalikan.')" />
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.status-toggle :user="$student->user" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data siswa.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->students->links() }}</div>
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
