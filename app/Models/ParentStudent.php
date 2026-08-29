<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `parent_student` pivot linking a student to an orang tua/wali.
 *
 * @property string|null $relationship
 */
class ParentStudent extends Pivot
{
    protected $table = 'parent_student';
}
