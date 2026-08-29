<?php

use App\Models\ParentGuardian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Edit Orang Tua')] class extends Component {
    use WithFileUploads;

    public ParentGuardian $parent;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public ?TemporaryUploadedFile $avatar = null;

    public function mount(ParentGuardian $parent): void
    {
        $this->parent = $parent;
        $this->name = $parent->name;
        $this->email = $parent->user?->email ?? '';
        $this->phone = $parent->phone;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->parent->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->avatar) {
            $data['avatar_url'] = Storage::url($this->avatar->store('parents', 'public'));

            if ($this->parent->avatar_url) {
                Storage::disk('public')->delete(Str::after($this->parent->avatar_url, '/storage/'));
            }
        }

        $email = $data['email'];
        unset($data['avatar'], $data['email']);

        DB::transaction(function () use ($data, $email): void {
            $this->parent->user?->update(['name' => $data['name'], 'email' => $email]);
            $this->parent->update($data);
        });

        toast(__('Data orang tua diperbarui.'), 'success');

        $this->redirectRoute('master-data.parents.index', navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header :title="__('Edit Orang Tua')" :subtitle="__('Perbarui data orang tua.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-back-outline" :href="route('master-data.parents.index')" wire:navigate>
                {{ __('Kembali') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        <div class="mb-8 flex flex-col gap-2" x-data="{ photoPreview: null }">
            <label class="font-semibold text-gray-600">{{ __('Foto Profil') }}</label>
            <span class="text-xs text-gray-500">{{ __('Keterangan Upload : (Maksimal: 2MB, Dimensi: 800x800 piksel)') }}</span>
            <div class="h-[200px] w-[200px] cursor-pointer rounded-2xl border-2 border-dashed border-primary-400 bg-gray-100"
                @click="$refs.avatar.click()">
                <img class="h-[196px] w-[196px] rounded-2xl object-cover"
                    src="{{ $parent->avatar_url ?? asset('assets/placeholder.png') }}"
                    x-bind:src="photoPreview ?? '{{ $parent->avatar_url ?? asset('assets/placeholder.png') }}'"
                    alt="{{ $parent->name }}">
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

        <div class="mb-8 grid grid-cols-1 gap-8 md:grid-cols-2">
            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Nama Lengkap') }} <span class="text-red-500">*</span></label>
                <input type="text" wire:model="name"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('name')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Telepon') }}</label>
                <input type="text" wire:model="phone"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('phone')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col">
                <label class="mb-1 font-semibold text-gray-600">{{ __('Email') }} <span class="text-red-500">*</span></label>
                <input type="email" wire:model="email"
                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('email')
                    <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('master-data.parents.index')" wire:navigate>
                {{ __('Batal') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" class="cursor-pointer">{{ __('Simpan') }}</x-ui.button>
        </div>
    </form>
</div>