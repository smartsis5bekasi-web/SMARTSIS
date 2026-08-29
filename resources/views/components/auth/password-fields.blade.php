@props([
    'label' => null,
    'confirmLabel' => null,
])

{{--
    Password + confirmation pair with live feedback.

    Only one thing is actually required — the minimum length from
    App\Support\PasswordPolicy, the same source `Password::defaults()` uses — and
    it is also folded into the `pattern` attribute so the browser refuses to
    submit a password the server would reject. Everything else the meter reacts
    to is advice: it colours the bar and fills the tip line, but never blocks the
    form. Once the length is met the password is accepted, first try.

        <x-auth.password-fields />
--}}
@php
    use App\Support\PasswordPolicy;

    $label ??= __('Password');
    $confirmLabel ??= __('Confirm password');
    $requirements = PasswordPolicy::requirements();
@endphp

<div
    class="flex flex-col gap-6"
    x-data="{
        password: '',
        confirmation: '',
        requirements: @js($requirements),
        suggestions: @js(PasswordPolicy::suggestions()),
        /** True once the password satisfies this one requirement or suggestion. */
        meets(check) {
            if (this.password === '') {
                return false;
            }

            if (check.minLength !== null && check.minLength !== undefined) {
                return [...this.password].length >= check.minLength;
            }

            return new RegExp(check.pattern, 'u').test(this.password);
        },
        get isValid() {
            return this.requirements.every((requirement) => this.meets(requirement));
        },
        get unmetTips() {
            return this.suggestions.filter((suggestion) => ! this.meets(suggestion)).map((suggestion) => suggestion.tip);
        },
        /** Advisory score: the required rule plus every suggestion taken. */
        get strengthPercent() {
            const checks = [...this.requirements, ...this.suggestions];
            const met = checks.filter((check) => this.meets(check)).length;

            return Math.round((met / checks.length) * 100);
        },
        get strengthLabel() {
            if (! this.isValid) {
                return @js(__('Terlalu pendek'));
            }

            if (this.unmetTips.length === 0) {
                return @js(__('Kuat'));
            }

            return this.unmetTips.length <= this.suggestions.length / 2
                ? @js(__('Cukup'))
                : @js(__('Bisa dipakai'));
        },
        get confirmationMismatch() {
            return this.confirmation !== '' && this.password !== this.confirmation;
        },
        /**
         * The browser cannot express `same as the field above` through an
         * attribute, so report the mismatch through the constraint API — that
         * keeps the block-before-submit behaviour consistent with `pattern`.
         */
        syncConfirmation() {
            this.$refs.confirmation?.setCustomValidity(
                this.confirmationMismatch ? @js(__('Konfirmasi kata sandi belum sama.')) : ''
            );
        },
    }"
    x-effect="syncConfirmation()"
>
    <div class="flex flex-col gap-3">
        <flux:input
            name="password"
            :label="$label"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="$label"
            pattern="{{ PasswordPolicy::htmlPattern() }}"
            title="{{ PasswordPolicy::summary() }}"
            passwordrules="{{ PasswordPolicy::rule()->toPasswordRulesString() }}"
            aria-describedby="password-requirements"
            viewable
            x-model="password"
            :invalid="$errors->has('password')"
            error:name=""
        />

        {{--
            Flux only renders the first message per field, so a password that
            misses several rules would surface them one submit at a time. List
            all of them at once instead — that is the loop this whole component
            exists to break.
        --}}
        @if ($errors->has('password'))
            <ul class="flex flex-col gap-1" role="alert" aria-live="polite">
                @foreach ($errors->get('password') as $error)
                    <li class="flex items-start gap-2 text-xs font-medium text-red-500">
                        <ion-icon class="mt-0.5 shrink-0 text-sm" name="alert-circle"></ion-icon>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Advisory strength meter: guidance only, it never blocks the form --}}
        <div class="flex items-center gap-3">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    x-bind:class="! isValid ? 'bg-red-400' : (unmetTips.length === 0 ? 'bg-green-500' : 'bg-secondary-500')"
                    x-bind:style="`width: ${strengthPercent}%`"
                    style="width: 0%"
                ></div>
            </div>
            <span
                class="text-xs font-semibold"
                x-bind:class="! isValid ? 'text-red-500' : (unmetTips.length === 0 ? 'text-green-600' : 'text-secondary-600')"
                x-text="password === '' ? @js(__('Belum diisi')) : strengthLabel"
            >{{ __('Belum diisi') }}</span>
        </div>

        {{-- The one thing that is actually required --}}
        <ul id="password-requirements" class="flex flex-col gap-1.5">
            @foreach ($requirements as $requirement)
                <li
                    class="flex items-center gap-2 text-xs transition-colors"
                    x-bind:class="meets(requirements[{{ $loop->index }}]) ? 'text-green-600' : 'text-gray-500'"
                >
                    <ion-icon
                        class="shrink-0 text-sm"
                        name="ellipse-outline"
                        x-bind:name="meets(requirements[{{ $loop->index }}]) ? 'checkmark-circle' : 'ellipse-outline'"
                    ></ion-icon>
                    {{ $requirement['label'] }}
                </li>
            @endforeach
        </ul>

        {{-- Already acceptable, with an optional nudge to make it sturdier --}}
        <p
            class="flex items-start gap-2 text-xs font-medium text-green-600"
            x-show="isValid"
            style="display: none"
        >
            <ion-icon class="mt-0.5 shrink-0 text-sm" name="checkmark-circle"></ion-icon>
            <span>
                {{ __('Kata sandi sudah memenuhi syarat dan bisa langsung disimpan.') }}
                <span
                    class="font-normal text-gray-500"
                    x-show="unmetTips.length > 0"
                    x-text="@js(__('Opsional, agar lebih aman:')) + ' ' + unmetTips.slice(0, 2).join(', ') + '.'"
                ></span>
            </span>
        </p>
    </div>

    <div class="flex flex-col gap-1.5">
        <flux:input
            name="password_confirmation"
            :label="$confirmLabel"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="$confirmLabel"
            viewable
            x-ref="confirmation"
            x-model="confirmation"
        />

        <p
            class="flex items-center gap-2 text-xs font-medium text-red-500"
            x-show="confirmationMismatch"
            style="display: none"
        >
            <ion-icon class="shrink-0 text-sm" name="close-circle"></ion-icon>
            {{ __('Konfirmasi kata sandi belum sama.') }}
        </p>

        <p
            class="flex items-center gap-2 text-xs font-medium text-green-600"
            x-show="confirmation !== '' && ! confirmationMismatch"
            style="display: none"
        >
            <ion-icon class="shrink-0 text-sm" name="checkmark-circle"></ion-icon>
            {{ __('Konfirmasi kata sandi sudah sama.') }}
        </p>
    </div>
</div>
