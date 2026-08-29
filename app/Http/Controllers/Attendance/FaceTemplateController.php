<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Face templates for the browser-side matcher.
 *
 * These used to be inlined into the scanner page's markup. Each student
 * carries three 128-float descriptors — roughly 4 KB of JSON — so a school-wide
 * kiosk was shipping megabytes, and Livewire re-sent all of it on every single
 * scan because the payload sits inside the component it re-renders.
 *
 * Serving them here means the browser fetches them once and then revalidates
 * with an ETag, so repeat loads cost a 304 instead of the whole set.
 */
class FaceTemplateController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        // A staffed kiosk matches 1:N against everyone; anyone else only ever
        // gets the record linked to their own account, which for a siswa is
        // their own template and for everybody else is nothing at all.
        $isKiosk = $user->can(Permission::ManageAttendance->value);

        $query = Student::query()
            ->whereNotNull('face_descriptors')
            ->unless($isKiosk, fn (Builder $inner) => $inner->where('user_id', $user->id));

        $scope = $isKiosk ? 'all' : 'user:'.$user->id;

        $version = md5(implode('|', [
            $scope,
            (string) $query->clone()->count(),
            (string) $query->clone()->max('face_registered_at'),
        ]));

        // Answer the revalidation before touching a single descriptor.
        if (in_array('"'.$version.'"', $request->getETags(), true)) {
            return response('', Response::HTTP_NOT_MODIFIED)->setEtag($version, weak: false);
        }

        $students = $query
            ->get(['id', 'name', 'face_descriptors'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'descriptors' => $student->face_descriptors,
            ])
            ->all();

        return response()
            ->json($students)
            ->setEtag($version, weak: false)
            ->setPrivate()
            ->setMaxAge(0)
            ->setSharedMaxAge(0);
    }
}
