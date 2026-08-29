<?php

namespace App\Models;

use Database\Factories\ParentGuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Parent/guardian (orang tua/wali) account profile.
 *
 * Mapped to the `parents` table; the class is named ParentGuardian because
 * "Parent" is a reserved word in PHP.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 */
class ParentGuardian extends Model
{
    /** @use HasFactory<ParentGuardianFactory> */
    use HasFactory;

    protected $table = 'parents';

    protected $fillable = ['user_id', 'name', 'avatar_url', 'phone', 'email', 'password'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The children (students) linked to this parent.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }
}
