<?php

use App\Enums\UserRole;
use App\Exports\Sheets\TeacherTemplateSheet;
use App\Exports\TeacherImportTemplate;
use App\Exports\TeachersExport;
use App\Livewire\Concerns\ImportsSpreadsheetRows;
use App\Models\Teacher;
use App\Models\User;
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
use App\Livewire\Concerns\TogglesUserActiveStatus; 

new #[Title('Guru')] class extends Component {
    use ImportsSpreadsheetRows;
    use WithFileUploads;
    use WithPagination;
    use TogglesUserActiveStatus;

    public bool $showImportModal = false;

    public ?TemporaryUploadedFile $importFile = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    /**
     * @return LengthAwarePaginator<int, Teacher>
     */
    #[Computed]
    public function teachers(): LengthAwarePaginator
    {
        return Teacher::query()
            ->with('user.roles')
            ->orderBy('name')
            ->paginate(10);
    }

    public function delete(Teacher $teacher): void
    {
        // Deleting the user cascades to the teacher row, which in turn nulls
        // any classroom it was the homeroom teacher of.
        $teacher->loadMissing('user');
        $teacher->user?->delete();
        $teacher->delete();

        $this->dispatch('swal', icon: 'success', title: __('Guru dihapus.'));
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
        return Excel::download(new TeachersExport, 'data-guru-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Download the .xlsx import template with sample rows plus a reference
     * sheet for the "peran" relation column.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new TeacherImportTemplate, 'template-import-guru.xlsx');
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

        [$rows, $formatError] = $this->parseImportFile(['nama', 'email', 'peran']);

        if ($formatError !== null) {
            $this->importErrors = [$formatError];

            return;
        }

        if ($rows === []) {
            $this->importErrors = [__('File tidak berisi data guru.')];

            return;
        }

        $prepared = [];
        $errors = [];
        $seenEmails = [];
        $seenNips = [];

        foreach ($rows as $line => $row) {
            $validator = Validator::make($row, [
                'nama' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'nip' => ['nullable', 'string', 'max:30', Rule::unique('teachers', 'nip')],
                'telepon' => ['nullable', 'string', 'max:30'],
                'peran' => ['required'],
                'password' => ['nullable', 'string', 'min:8'],
            ], [
                'nama.required' => __('kolom nama wajib diisi'),
                'nama.max' => __('nama maksimal 100 karakter'),
                'email.required' => __('kolom email wajib diisi'),
                'email.email' => __('format email tidak valid'),
                'email.max' => __('email maksimal 255 karakter'),
                'email.unique' => __('email sudah terdaftar'),
                'nip.max' => __('NIP maksimal 30 karakter'),
                'nip.unique' => __('NIP sudah terdaftar'),
                'telepon.max' => __('telepon maksimal 30 karakter'),
                'peran.required' => __('kolom peran wajib diisi'),
                'password.min' => __('password minimal 8 karakter'),
            ]);

            $rowErrors = $validator->errors()->all();

            $role = isset($row['peran']) ? $this->resolveRole($row['peran']) : null;

            if (isset($row['peran']) && $role === null) {
                $rowErrors[] = __('peran ":value" tidak dikenali, gunakan salah satu: :options', [
                    'value' => $row['peran'],
                    'options' => implode(', ', array_map(fn (UserRole $option): string => $option->value, UserRole::teacherRoles())),
                ]);
            }

            $email = mb_strtolower((string) ($row['email'] ?? ''));

            if ($email !== '' && isset($seenEmails[$email])) {
                $rowErrors[] = __('email duplikat dengan baris :line', ['line' => $seenEmails[$email]]);
            }

            if (isset($row['nip'], $seenNips[$row['nip']])) {
                $rowErrors[] = __('NIP duplikat dengan baris :line', ['line' => $seenNips[$row['nip']]]);
            }

            if ($rowErrors !== []) {
                $errors[] = __('Baris :line: :messages', ['line' => $line, 'messages' => implode('; ', $rowErrors)]);

                continue;
            }

            $seenEmails[$email] = $line;

            if (isset($row['nip'])) {
                $seenNips[$row['nip']] = $line;
            }

            $prepared[] = [
                'name' => $row['nama'],
                'email' => $row['email'],
                'nip' => $row['nip'] ?? null,
                'phone' => $row['telepon'] ?? null,
                'role' => $role,
                'password' => $row['password'] ?? 'password',
            ];
        }

        if ($errors !== []) {
            $this->importErrors = $errors;

            return;
        }

        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as $row) {
                $user = User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $row['password'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($row['role']->value);

                Teacher::create([
                    'user_id' => $user->id,
                    'name' => $row['name'],
                    'nip' => $row['nip'],
                    'phone' => $row['phone'],
                ]);
            }
        });

        $this->closeImportModal();
        $this->resetPage();

        $this->dispatch('swal', icon: 'success', title: __(':count guru berhasil diimpor.', ['count' => count($prepared)]));
    }

    /**
     * Template header + sample rows shown in the modal, sourced from the
     * downloadable template so they never drift apart.
     *
     * @return array<int, array<int, string>>
     */
    public function sampleRows(): array
    {
        $sheet = new TeacherTemplateSheet;

        return [$sheet->headings(), ...$sheet->array()];
    }

    /**
     * Accept either the enum value (guru_mapel) or the label (Guru Mata
     * Pelajaran), case-insensitively.
     */
    private function resolveRole(string $value): ?UserRole
    {
        $needle = mb_strtolower(trim($value));

        foreach (UserRole::teacherRoles() as $role) {
            if ($needle === $role->value || $needle === mb_strtolower($role->label())) {
                return $role;
            }
        }

        return null;
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Guru')" :subtitle="__('Kelola data guru beserta akun & peran login.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download-outline" wire:click="export">
                {{ __('Export') }}
            </x-ui.button>
            <x-ui.button variant="secondary" icon="cloud-upload-outline" wire:click="openImportModal">
                {{ __('Import') }}
            </x-ui.button>
            <x-ui.button variant="primary" icon="add-outline" :href="route('master-data.teachers.create')" wire:navigate>
                {{ __('Tambah') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500">
                        <th class="px-4 py-3 font-medium">{{ __('Nama') }}</th>
                        <th class="px-4 py-3 font-medium">NIP</th>
                        <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Peran') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Telepon') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Aksi') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($this->teachers as $teacher)
                        <tr wire:key="{{ $teacher->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $teacher->name }}</td>
                            <td class="px-4 py-3">{{ $teacher->nip ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $teacher->user?->email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($teacher->user?->primaryRole())
                                    <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">
                                        {{ $teacher->user->primaryRole()->label() }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $teacher->phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('master-data.teachers.edit', $teacher) }}" wire:navigate class="inline-flex text-primary-600 transition hover:text-primary-700" title="{{ __('Edit') }}">
                                        <ion-icon name="create-outline" class="text-xl"></ion-icon>
                                    </a>
                                    <x-ui.delete-button :wire-id="$teacher->id" :text="__('Akun login terkait juga akan dihapus dan tidak dapat dikembalikan.')" />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-ui.status-toggle :user="$teacher->user" :method="'toggleActive'" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ __('Belum ada data guru.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->teachers->links() }}</div>
    </div>

    @if ($showImportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeImportModal()">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeImportModal"></div>

            <div class="relative flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Import Guru') }}</h2>
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
                        {{ __('lalu isi datanya sesuai contoh di bawah. Daftar nilai peran juga tersedia di sheet "Referensi Peran" pada template.') }}
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
                        <p class="mb-1.5 font-semibold">{{ __('Kolom peran diisi salah satu dari:') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (\App\Enums\UserRole::teacherRoles() as $role)
                                <span wire:key="role-option-{{ $role->value }}" class="inline-flex rounded-full bg-white px-2.5 py-0.5 font-medium text-primary-700">
                                    {{ $role->value }} ({{ $role->label() }})
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <ul class="list-inside list-disc space-y-1 text-xs text-gray-500">
                        <li>{{ __('Kolom nama, email, dan peran wajib diisi; email & NIP tidak boleh sama dengan data yang sudah ada.') }}</li>
                        <li>{{ __('Kolom password opsional — jika dikosongkan, akun dibuat dengan password "password".') }}</li>
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
