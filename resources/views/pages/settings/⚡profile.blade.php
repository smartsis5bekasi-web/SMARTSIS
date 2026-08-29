<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Teacher;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Settings → Profile.
 *
 * Everyone edits their photo, name and email. Beyond that, which fields appear
 * follows the profile record behind the account, not the role name: a student
 * record brings gender/birth date/address, a teacher or parent record brings a
 * phone number, and an account with neither (super admin, staff) simply sees
 * the three shared fields.
 *
 * Administrative data — NIS, class, NIP — is displayed but never editable here;
 * that stays with the admin in master data.
 */
new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';

    /** Teachers and parents/guardians. */
    public ?string $phone = null;

    /** Students. */
    public ?string $gender = null;
    public ?string $birth_date = null;
    public ?string $address = null;

    public ?TemporaryUploadedFile $photo = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;

        if ($student = $this->student) {
            $this->gender = $student->gender;
            $this->birth_date = $student->birth_date?->format('Y-m-d');
            $this->address = $student->address;
        }

        $this->phone = $this->teacher?->phone ?? $this->parentGuardian?->phone;
    }

    #[Computed]
    public function student(): ?Student
    {
        return Auth::user()->student()->with(['classroom', 'major'])->first();
    }

    #[Computed]
    public function teacher(): ?Teacher
    {
        return Auth::user()->teacher()->first();
    }

    #[Computed]
    public function parentGuardian(): ?ParentGuardian
    {
        return Auth::user()->parentGuardian()->with('students')->first();
    }

    /**
     * Whether a phone number belongs on this profile — teachers and parents
     * are reachable by phone, students are reached through their guardians.
     */
    #[Computed]
    public function hasPhone(): bool
    {
        return $this->teacher !== null || $this->parentGuardian !== null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function genderOptions(): array
    {
        return [
            ['value' => 'L', 'label' => __('Laki-laki')],
            ['value' => 'P', 'label' => __('Perempuan')],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            ...$this->profileRules(Auth::id()),
            'photo' => ['nullable', 'image', 'max:2048'],
        ];

        if ($this->hasPhone) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        if ($this->student !== null) {
            // Same age window master data enforces, but optional here: nobody
            // should be blocked from changing their photo by a missing birthday.
            $rules['gender'] = ['nullable', Rule::in(['L', 'P'])];
            $rules['birth_date'] = [
                'nullable', 'date',
                'after_or_equal:'.now()->subYears(20)->format('Y-m-d'),
                'before_or_equal:'.now()->subYears(14)->format('Y-m-d'),
            ];
            $rules['address'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'birth_date.before_or_equal' => __('Umur siswa minimal harus 14 tahun.'),
            'birth_date.after_or_equal' => __('Umur siswa maksimal 20 tahun.'),
        ];
    }

    /**
     * Update the profile information for the currently authenticated user.
     *
     * The name is mirrored onto the profile record so master data and the
     * account never show two different names for the same person.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate();

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->photo !== null) {
            $this->replaceAvatar($user->avatarUrl());
        }

        $this->student?->update([
            'name' => $validated['name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'],
            'address' => $validated['address'],
        ]);

        $this->teacher?->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $this->parentGuardian?->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        unset($this->student, $this->teacher, $this->parentGuardian, $this->hasPhone);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Store the uploaded photo and drop the file it replaces, so old avatars do
     * not pile up on disk.
     */
    protected function replaceAvatar(?string $previousUrl): void
    {
        $url = Storage::url($this->photo->store('avatars', 'public'));

        Auth::user()->storeAvatarUrl($url);
        $this->photo = null;

        $this->deleteStoredFile($previousUrl);
    }

    public function removePhoto(): void
    {
        $previousUrl = Auth::user()->avatarUrl();

        Auth::user()->storeAvatarUrl(null);
        $this->photo = null;

        unset($this->student, $this->teacher, $this->parentGuardian);

        $this->deleteStoredFile($previousUrl);

        Flux::toast(variant: 'success', text: __('Foto profil dihapus.'));
    }

    protected function deleteStoredFile(?string $url): void
    {
        if (filled($url) && str_contains($url, '/storage/')) {
            Storage::disk('public')->delete(Str::after($url, '/storage/'));
        }
    }

    /**
     * Read-only data the admin owns. Null for accounts with no profile record.
     *
     * @return array{heading: string, rows: array<string, ?string>}|null
     */
    #[Computed]
    public function administrativeData(): ?array
    {
        $role = Auth::user()->primaryRole()?->label();

        if ($student = $this->student) {
            return [
                'heading' => __('Data Sekolah'),
                'rows' => [
                    __('NIS') => $student->nis,
                    __('NISN') => $student->nisn,
                    __('Kelas') => $student->classroom?->name,
                    __('Jurusan') => $student->major?->name,
                ],
            ];
        }

        if ($teacher = $this->teacher) {
            return [
                'heading' => __('Data Kepegawaian'),
                'rows' => [
                    __('NIP') => $teacher->nip,
                    __('Peran') => $role,
                ],
            ];
        }

        if ($parent = $this->parentGuardian) {
            return [
                'heading' => __('Data Akun'),
                'rows' => [
                    __('Peran') => $role,
                    __('Anak') => $parent->students->pluck('name')->join(', ') ?: null,
                ],
            ];
        }

        return null;
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Perbarui foto dan data pribadi Anda')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            {{-- Profile photo --}}
            <div class="flex flex-col gap-2" x-data="{ preview: null }">
                <flux:label>{{ __('Foto Profil') }}</flux:label>
                <flux:text size="sm">{{ __('Maksimal 2MB. Format JPG, PNG, atau WEBP.') }}</flux:text>

                <div class="mt-1 flex items-center gap-4">
                    @php $avatarUrl = auth()->user()->avatarUrl(); @endphp

                    <button
                        type="button"
                        x-on:click="$refs.photo.click()"
                        class="h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-dashed border-primary-400 bg-gray-100 transition hover:border-primary-600"
                        title="{{ __('Ganti foto') }}"
                    >
                        <img
                            class="h-full w-full object-cover"
                            @if ($avatarUrl) src="{{ $avatarUrl }}" @endif
                            x-bind:src="preview ?? @js($avatarUrl)"
                            x-show="preview !== null || @js($avatarUrl !== null)"
                            @unless ($avatarUrl) style="display: none" @endunless
                            alt="{{ auth()->user()->name }}"
                        >

                        <span
                            class="flex h-full w-full items-center justify-center text-lg font-semibold text-gray-500"
                            x-show="preview === null && @js($avatarUrl === null)"
                            @if ($avatarUrl) style="display: none" @endif
                        >{{ auth()->user()->initials() }}</span>
                    </button>

                    <div class="flex flex-col items-start gap-1">
                        <flux:button size="sm" variant="filled" x-on:click="$refs.photo.click()">
                            {{ __('Pilih foto') }}
                        </flux:button>

                        @if ($avatarUrl)
                            <flux:button
                                size="sm"
                                variant="subtle"
                                wire:click="removePhoto"
                                wire:confirm="{{ __('Hapus foto profil?') }}"
                            >
                                {{ __('Hapus foto') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <input
                    type="file"
                    wire:model="photo"
                    x-ref="photo"
                    class="hidden"
                    accept="image/*"
                    x-on:change="
                        const file = $refs.photo.files[0];
                        if (! file) { preview = null; return; }
                        const reader = new FileReader();
                        reader.onload = (event) => { preview = event.target.result; };
                        reader.readAsDataURL(file);
                    "
                >

                <flux:text size="sm" wire:loading wire:target="photo">{{ __('Mengunggah…') }}</flux:text>
                <flux:error name="photo" />
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            {{-- Personal fields, driven by the profile record behind the account --}}
            @if ($this->hasPhone)
                <flux:input
                    wire:model="phone"
                    :label="__('Telepon')"
                    type="text"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="08123456789"
                />
            @endif

            @if ($this->student)
                <flux:select wire:model="gender" :label="__('Jenis Kelamin')" :placeholder="__('Pilih jenis kelamin')">
                    @foreach ($this->genderOptions() as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="birth_date"
                    :label="__('Tanggal Lahir')"
                    type="date"
                    min="{{ now()->subYears(20)->format('Y-m-d') }}"
                    max="{{ now()->subYears(14)->format('Y-m-d') }}"
                />

                <flux:textarea wire:model="address" :label="__('Alamat')" rows="3" :placeholder="__('Alamat lengkap')" />
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>

        {{-- Administrative data: shown for reference, changed by an admin --}}
        @if ($this->administrativeData)
            <div class="mt-10 space-y-4">
                <div>
                    <flux:heading>{{ $this->administrativeData['heading'] }}</flux:heading>
                    <flux:subheading>{{ __('Dikelola oleh admin. Hubungi admin jika ada yang perlu diperbaiki.') }}</flux:subheading>
                </div>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 rounded-lg border border-gray-100 bg-gray-50 p-4 sm:grid-cols-2">
                    @foreach ($this->administrativeData['rows'] as $label => $value)
                        <div class="flex flex-col">
                            <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                            <dd class="text-sm font-semibold text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
