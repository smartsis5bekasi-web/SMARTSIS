<?php

namespace App\Enums;

use App\Actions\Student\RegisterStudentFace;

/**
 * What a staff member standing in front of both the camera and the student
 * may do when a capture lands close to a face already enrolled on another
 * student. See {@see RegisterStudentFace}.
 */
enum FaceConflictResolution: string
{
    /**
     * The other face was only self-registered, so nobody ever checked it was
     * theirs — often it is the student now in front of the camera, enrolled
     * by a classmate. The in-person capture wins and the other student has to
     * be registered again, in person.
     */
    case Transfer = 'transfer';

    /**
     * Both faces were seen by staff and are not near-identical: two
     * look-alike students. Both templates are kept and the kiosk refuses to
     * pick between them when a scan is ambiguous.
     */
    case KeepBoth = 'keep-both';
}
