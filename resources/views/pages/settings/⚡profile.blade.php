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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';

    public ?string $phone = null;
    public ?string $address = null;

    public ?TemporaryUploadedFile $avatar = null;

    /**
     * Which profile table backs the signed-in user, or null if none
     * (e.g. Super Admin has no linked student/teacher/parent record).
     */
    public ?string $profileType = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;

        $profile = $this->profile;

        if ($profile instanceof Student) {
            $this->profileType = 'student';
            $this->address = $profile->address;
        } elseif ($profile instanceof Teacher) {
            $this->profileType = 'teacher';
            $this->phone = $profile->phone;
        } elseif ($profile instanceof ParentGuardian) {
            $this->profileType = 'parent';
            $this->phone = $profile->phone;
        }
    }

    /**
     * The role-specific profile record backing this account, if any.
     */
    #[Computed]
    public function profile(): Student|Teacher|ParentGuardian|null
    {
        $user = Auth::user();

        return $user->student ?? $user->teacher ?? $user->parentGuardian;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = $this->profileRules($user->id);
        $rules['avatar'] = ['nullable', 'image', 'max:2048'];

        if ($this->profileType === 'student') {
            $rules['address'] = ['nullable', 'string', 'max:255'];
        }

        if (in_array($this->profileType, ['teacher', 'parent'], true)) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        $validated = $this->validate($rules);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $profile = $this->profile;

        if ($profile) {
            $profileData = ['name' => $validated['name']];

            if ($this->avatar) {
                $folder = match ($this->profileType) {
                    'student' => 'students',
                    'teacher' => 'teachers',
                    'parent' => 'parents',
                    default => 'avatars',
                };

                $profileData['avatar_url'] = Storage::url($this->avatar->store($folder, 'public'));

                if ($profile->avatar_url) {
                    Storage::disk('public')->delete(Str::after($profile->avatar_url, '/storage/'));
                }
            }

            if ($this->profileType === 'student') {
                $profileData['address'] = $validated['address'] ?? null;
            }

            if (in_array($this->profileType, ['teacher', 'parent'], true)) {
                $profileData['phone'] = $validated['phone'] ?? null;
            }

            $profile->update($profileData);
        }

        $this->reset('avatar');

        Flux::toast(variant: 'success', text: __('Profile updated.'));
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

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
    @if ($profileType)
        <div class="flex flex-col gap-2" x-data="{ photoPreview: null }">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Foto Profil') }}</label>
            <div class="flex items-center gap-4">
                <div class="h-20 w-20 cursor-pointer rounded-full border-2 border-dashed border-primary-400 bg-gray-100"
                    @click="$refs.avatar.click()">
                    <img class="h-full w-full rounded-full object-cover"
                        src="{{ $this->profile?->avatar_url ?? asset('assets/placeholder.png') }}"
                        x-bind:src="photoPreview ?? '{{ $this->profile?->avatar_url ?? asset('assets/placeholder.png') }}'"
                        alt="{{ __('Foto Profil') }}">
                </div>
                <div class="flex flex-col gap-1">
                    <button type="button" @click="$refs.avatar.click()" class="text-sm font-medium text-primary-600 hover:underline">
                        {{ __('Ganti Foto') }}
                    </button>
                    <span class="text-xs text-gray-500">{{ __('Maksimal 2MB.') }}</span>
                </div>
            </div>
            <input type="file" wire:model="avatar" x-ref="avatar" class="hidden" accept="image/*"
                x-on:change="
                    const file = $refs.avatar.files[0];
                    if (! file) { photoPreview = null; return; }
                    const reader = new FileReader();
                    reader.onload = (e) => { photoPreview = e.target.result; };
                    reader.readAsDataURL(file);
                ">
            <div wire:loading wire:target="avatar" class="text-xs text-gray-500">{{ __('Mengunggah…') }}</div>
            @error('avatar')
                <span class="text-sm text-red-500">{{ $message }}</span>
            @enderror
        </div>
    @endif

    <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

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

             @if ($profileType === 'student')
        <flux:input wire:model="address" :label="__('Alamat')" type="text" />
    @endif

    @if (in_array($profileType, ['teacher', 'parent'], true))
        <flux:input wire:model="phone" :label="__('Telepon')" type="text" placeholder="08123456789" />
    @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
