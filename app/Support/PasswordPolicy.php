<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for how strong a SMARTSIS password has to be.
 *
 * The policy is deliberately light: one rule, a minimum length, applied the
 * same way in every environment. Students reset their own passwords, so extra
 * gates (complexity classes, breached-password lookups) mostly produced repeat
 * failures rather than safer accounts. Anything beyond the minimum is offered
 * as advice through `suggestions()`, which only colours the strength meter and
 * never blocks a submission.
 *
 * The same definition drives three things that would otherwise drift apart:
 *
 *  - server-side validation, through `Password::defaults()`;
 *  - the live checklist the user sees while typing;
 *  - the `pattern` attribute the browser enforces before the form is submitted.
 */
final class PasswordPolicy
{
    /** The only requirement a password must meet. */
    public const MIN_LENGTH = 8;

    /** Length at which the advisory meter stops asking for more. */
    public const COMFORTABLE_LENGTH = 12;

    public static function minLength(): int
    {
        return self::MIN_LENGTH;
    }

    /**
     * The validation rule. Registered as the application default in
     * AppServiceProvider, so `Password::default()` resolves to this.
     *
     * Intentionally free of `uncompromised()`: once the length check passes the
     * password is accepted, with no second round of server-side rejections.
     */
    public static function rule(): Password
    {
        return Password::min(self::MIN_LENGTH);
    }

    /**
     * Requirements the browser can check on every keystroke. Length is counted
     * in code points, the way Laravel counts it.
     *
     * @return array<int, array{key: string, label: string, minLength: int}>
     */
    public static function requirements(): array
    {
        return [[
            'key' => 'length',
            'label' => __('Minimal :count karakter', ['count' => self::MIN_LENGTH]),
            'minLength' => self::MIN_LENGTH,
        ]];
    }

    /**
     * Advice, not rules. These feed the strength meter and the one-line tip
     * under it; a password that ignores every one of them is still accepted.
     *
     * `pattern` is a lookahead-only regular expression, valid in both PCRE and
     * JavaScript under the unicode flag, mirroring the classes Laravel's own
     * `Password` rule uses (`\p{Ll}`/`\p{Lu}`, `\p{N}`, `\p{Z}\p{S}\p{P}`).
     *
     * @return array<int, array{key: string, tip: string, minLength: int|null, pattern: string|null}>
     */
    public static function suggestions(): array
    {
        return [
            [
                'key' => 'comfortable-length',
                'tip' => __('panjangkan sampai :count karakter', ['count' => self::COMFORTABLE_LENGTH]),
                'minLength' => self::COMFORTABLE_LENGTH,
                'pattern' => null,
            ],
            [
                'key' => 'mixed-case',
                'tip' => __('campur huruf besar dan kecil'),
                'minLength' => null,
                'pattern' => '(?=.*\p{Ll})(?=.*\p{Lu})',
            ],
            [
                'key' => 'numbers',
                'tip' => __('tambahkan angka'),
                'minLength' => null,
                'pattern' => '(?=.*\p{N})',
            ],
            [
                'key' => 'symbols',
                'tip' => __('tambahkan simbol seperti ! ? @ #'),
                'minLength' => null,
                'pattern' => '(?=.*[\p{Z}\p{S}\p{P}])',
            ],
        ];
    }

    /**
     * The requirements folded into one regular expression for the `pattern`
     * attribute, so the browser blocks a too-short password before the request
     * is ever sent. Browsers compile `pattern` in unicode mode and implicitly
     * anchor it at both ends.
     */
    public static function htmlPattern(): string
    {
        return '.{'.self::MIN_LENGTH.',}';
    }

    /**
     * One-line summary used as the `title` of the password field — browsers
     * append it to the message shown when `pattern` rejects the value.
     */
    public static function summary(): string
    {
        return implode(', ', array_column(self::requirements(), 'label')).'.';
    }
}
