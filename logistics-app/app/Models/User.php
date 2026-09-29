<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** What a Field Personnel account does on the truck. */
    public const FIELD_POSITIONS = [
        'driver' => 'Driver',
        'helper' => 'Truck / Cargo Helper',
    ];

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
            'role' => Role::class,
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }

    public function isHelper(): bool
    {
        return $this->role === Role::FieldPersonnel && $this->field_position === 'helper';
    }

    /**
     * The role as shown to people, e.g. "Field Personnel · Driver". English by default, so
     * activity log entries stay in one language; pass true to show it in the viewer's language.
     */
    public function roleLabel(bool $translated = false): string
    {
        $t = fn (string $s) => $translated ? __($s) : $s;

        if ($this->role !== Role::FieldPersonnel) {
            return $t($this->role->label());
        }

        return $t($this->role->label()).' · '.$t(match ($this->field_position) {
            'driver' => 'Driver',
            'helper' => 'Helper',
            default => 'Position not set',
        });
    }

    /** The profile photo, stored as a site_images row owned by this user. */
    public function avatar(): HasOne
    {
        return $this->hasOne(SiteImage::class);
    }

    /** The driver record this account signs in as (Field Personnel). */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class, 'user_id');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar?->url;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))
            ->take(2)->implode('');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }
}
