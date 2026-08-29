<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_url
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student|null $student
 * @property-read Teacher|null $teacher
 * @property-read ParentGuardian|null $parentGuardian
 */
#[Fillable(['name', 'email', 'avatar_url', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** Memo for {@see avatarOwner()}; null is a real answer, hence the flag. */
    private Student|Teacher|null $avatarOwner = null;

    private bool $avatarOwnerResolved = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The teacher profile linked to this account, if any.
     *
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * The student profile linked to this account, if any.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * The parent/guardian profile linked to this account, if any.
     *
     * @return HasOne<ParentGuardian, $this>
     */
    public function parentGuardian(): HasOne
    {
        return $this->hasOne(ParentGuardian::class);
    }

    /**
     * Whether this account is permitted to authenticate.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * The user's highest-priority role, used to pick which dashboard to render.
     *
     * Priority follows the declaration order of {@see UserRole} (Super Admin
     * first). Returns null when the account holds none of the known roles.
     */
    public function primaryRole(): ?UserRole
    {
        $assigned = $this->getRoleNames()->all();

        foreach (UserRole::cases() as $role) {
            if (in_array($role->value, $assigned, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The record this account's photo lives on.
     *
     * Students and teachers already carry an official photo on their profile
     * record, which is what the rest of the app (tables, letters, the kiosk)
     * shows — so the account reuses it rather than keeping a second copy that
     * could drift. Accounts with neither profile keep the photo on their own
     * row and get null here.
     *
     * Resolved through the relation query rather than the magic property so
     * the "no profile" case stays visible in the type, and memoised because
     * the layout asks for the avatar several times per page.
     */
    private function avatarOwner(): Student|Teacher|null
    {
        if (! $this->avatarOwnerResolved) {
            $this->avatarOwner = $this->student()->first() ?? $this->teacher()->first();
            $this->avatarOwnerResolved = true;
        }

        return $this->avatarOwner;
    }

    /**
     * The photo shown for this account, if it has one.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatarOwner()->avatar_url ?? $this->avatar_url;
    }

    /**
     * Write a new photo back to whichever record {@see avatarUrl()} reads from,
     * so the account photo and the profile photo can never disagree.
     */
    public function storeAvatarUrl(?string $url): void
    {
        $owner = $this->avatarOwner();

        if ($owner !== null) {
            $owner->update(['avatar_url' => $url]);

            return;
        }

        $this->update(['avatar_url' => $url]);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
