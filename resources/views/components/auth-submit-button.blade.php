@props([
    'loadingLabel' => __('Memproses...'),
])

{{--
    Submit button for the auth forms that swaps to a spinner once the form is
    actually being submitted.

    The browser runs native constraint validation *before* firing `submit`, so
    the busy state only appears after every required/type check has passed — a
    form that fails validation never shows the spinner.

        <x-auth-submit-button :loading-label="__('Masuk...')">
            {{ __('Log in') }}
        </x-auth-submit-button>
--}}
<div
    class="w-full"
    x-data="{ busy: false }"
    x-init="
        $el.closest('form')?.addEventListener('submit', () => busy = true);
        window.addEventListener('pageshow', () => busy = false);
    "
>
    <x-ui.button
        type="submit"
        x-bind:disabled="busy"
        x-bind:aria-busy="busy"
        {{ $attributes->merge(['class' => 'w-full']) }}
    >
        <svg
            x-show="busy"
            style="display: none"
            class="size-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>

        <span x-show="!busy">{{ $slot }}</span>
        <span x-show="busy" style="display: none">{{ $loadingLabel }}</span>
    </x-ui.button>
</div>
