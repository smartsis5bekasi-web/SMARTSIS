<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        @if (session('status'))
            <div class="flex gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">
                <span class="mt-0.5 shrink-0 text-lg leading-none text-green-600">
                    <ion-icon name="mail-outline"></ion-icon>
                </span>
                <div class="flex flex-col gap-1 text-start">
                    <span class="font-semibold">{{ session('status') }}</span>
                    <span class="text-green-700">
                        {{ __('Check your inbox — including the Spam and Promotions folders. The link expires in :minutes minutes.', ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)]) }}
                    </span>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <x-auth-submit-button :loading-label="__('Sending link...')" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </x-auth-submit-button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
