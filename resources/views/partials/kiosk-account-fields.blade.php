<div class="mb-8 grid grid-cols-1 gap-8 md:grid-cols-2">
    <div class="flex flex-col">
        <label class="mb-1 font-semibold text-gray-600" for="name">{{ __('Nama Perangkat') }} <span class="text-red-500">*</span></label>
        <input id="name" type="text" wire:model="name" placeholder="Tablet XI IPA 1"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        @error('name')
            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
        @enderror
    </div>

    <div class="flex flex-col">
        <label class="mb-1 font-semibold text-gray-600" for="email">{{ __('Email Login') }} <span class="text-red-500">*</span></label>
        <input id="email" type="email" wire:model="email" placeholder="kiosk.xi-ipa-1@sman5bekasi.sch.id"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        @error('email')
            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
        @enderror
    </div>

    <div class="flex flex-col">
        <label class="mb-1 font-semibold text-gray-600" for="password">
            {{ __('Password') }}
            @if ($passwordRequired)
                <span class="text-red-500">*</span>
            @endif
        </label>
        <input id="password" type="password" wire:model="password" autocomplete="new-password"
            placeholder="{{ $passwordRequired ? __('Minimal 8 karakter') : __('Kosongkan jika tidak diubah') }}"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        @error('password')
            <span class="mt-1 text-sm text-red-500">{{ $message }}</span>
        @enderror
    </div>
</div>
