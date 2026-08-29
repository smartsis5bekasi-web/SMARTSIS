<?php

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Run a password through the regular expression the browser is given, the same
 * way the browser does: implicitly anchored, in unicode mode.
 */
function matchesHtmlPattern(string $password): bool
{
    return preg_match('/^(?:'.PasswordPolicy::htmlPattern().')$/u', $password) === 1;
}

function passesValidation(string $password): bool
{
    return Validator::make(
        ['password' => $password],
        ['password' => PasswordPolicy::rule()],
    )->passes();
}

test('length is the only thing a password has to satisfy', function () {
    expect(PasswordPolicy::minLength())->toBe(8)
        ->and(PasswordPolicy::requirements())->toHaveCount(1)
        ->and(PasswordPolicy::requirements()[0]['key'])->toBe('length');
});

test('a password is accepted as soon as it is long enough', function (string $password, bool $acceptable) {
    // No breach lookup, no complexity classes: whatever the browser lets
    // through, the server accepts too, so one submit is always enough.
    expect(matchesHtmlPattern($password))->toBe($acceptable)
        ->and(passesValidation($password))->toBe($acceptable);
})->with([
    'plain but long enough' => ['sandisiswa', true],
    'exactly the minimum' => ['sandi123', true],
    'a common password is still fine' => ['password', true],
    'letters, digits and symbols' => ['Sandi#Kuat2026', true],
    'accented letters count as one character each' => ['sandiké12', true],
    'one short' => ['sandi12', false],
    'one short, with an accent' => ['sandiké', false],
]);

test('the password is never checked against a breach list', function () {
    // Any outbound call would fail this test, which is the point: the
    // uncompromised() lookup was dropped on purpose.
    Http::preventStrayRequests();

    expect(passesValidation('password'))->toBeTrue()
        ->and(PasswordPolicy::rule()->toPasswordRulesString())->toBe('minlength: 8;');
});

test('suggestions are advice only and never affect validation', function () {
    $weakButValid = 'sandisiswa';

    expect(passesValidation($weakButValid))->toBeTrue();

    foreach (PasswordPolicy::suggestions() as $suggestion) {
        if ($suggestion['pattern'] === null) {
            continue;
        }

        // Lookahead-only patterns have to work on their own, since Alpine runs
        // each one separately to score the meter.
        expect(preg_match('/'.$suggestion['pattern'].'/u', 'Sandi#Kuat2026'))->toBe(1)
            ->and(preg_match('/'.$suggestion['pattern'].'/u', $weakButValid))->toBe(0);
    }
});

test('the reset form ships the policy to the browser', function () {
    $response = $this->get(route('password.reset', 'a-reset-token'));

    $response->assertOk()
        // The browser blocks a too-short password before the request is sent...
        ->assertSee('pattern="'.e(PasswordPolicy::htmlPattern()).'"', false)
        // ...and the user is told what is needed while typing.
        ->assertSee('Minimal 8 karakter')
        ->assertSee('Kata sandi sudah memenuhi syarat dan bisa langsung disimpan.')
        ->assertSee('Konfirmasi kata sandi belum sama.')
        // Nothing about breached passwords is promised any more.
        ->assertDontSee('kebocoran');
});

test('a password that satisfies the checklist is accepted on the first submit', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'sandisiswa',
            'password_confirmation' => 'sandisiswa',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('a rejected password comes back with the reason spelled out on the page', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $resetUrl = route('password.reset', ['token' => $notification->token, 'email' => $user->email]);

        $expected = Validator::make(['password' => 'sandi'], ['password' => PasswordPolicy::rule()])
            ->errors()
            ->get('password');

        // Flux renders only the first message per field; the component lists
        // every one of them so a single correction is enough.
        $page = $this->from($resetUrl)
            ->followingRedirects()
            ->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'sandi',
                'password_confirmation' => 'sandi',
            ])
            ->assertOk();

        foreach ($expected as $message) {
            $page->assertSee($message);
        }

        return true;
    });
});

test('the application default password rule comes from the policy', function () {
    expect(Password::default()->toPasswordRulesString())
        ->toBe(PasswordPolicy::rule()->toPasswordRulesString());
});
