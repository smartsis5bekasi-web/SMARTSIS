<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force users with the Siswa role through the first-login onboarding
 * (NISN verification + face registration) before using the app.
 *
 * A student who already onboarded but has no face template is sent back to
 * the face step on every login, yet may skip it for the rest of that session
 * ({@see self::FACE_DEFERRED}) so a capture they cannot complete on their
 * own — a camera problem, or a face refused as another student's — never
 * locks them out of the app.
 */
class EnsureStudentIsOnboarded
{
    /** Session flag: the student chose to register their face later. */
    public const FACE_DEFERRED = 'onboarding.face_deferred';

    /** Session flag: a self-capture was refused as another student's face. */
    public const FACE_REFUSED = 'onboarding.face_refused';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(UserRole::Siswa->value)) {
            return $next($request);
        }

        $student = $user->student;

        if ($student === null || ($student->needsOnboarding() && ! $this->deferredFace($request, $student))) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }

    /**
     * Whether the only thing missing is a face the student put off until
     * later in this session.
     */
    private function deferredFace(Request $request, Student $student): bool
    {
        return $student->hasCompletedOnboarding() && $request->session()->get(self::FACE_DEFERRED, false);
    }
}
