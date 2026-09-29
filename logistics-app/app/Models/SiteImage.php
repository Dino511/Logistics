<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SiteImage extends Model
{
    /** Image slots the app uses, with the static file shown until one is uploaded. */
    public const SLOTS = [
        'login_background_portrait' => ['label' => 'Login & sidebar background (portrait)', 'fallback' => 'images/logistics-bg-portrait.jpg'],
        'login_background_landscape' => ['label' => 'Login background (landscape, wide screens & mobile)', 'fallback' => 'images/logistics-bg-landscape.jpg'],
    ];

    protected $fillable = ['user_id', 'name', 'path', 'disk'];

    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk($this->disk)->url($this->path));
    }

    /** Set for profile photos; null for app-wide images like the login background. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function avatarNameFor(User $user): string
    {
        return "user_avatar_{$user->id}";
    }

    /** Replace a user's profile photo: the old file and row go, the new one is stored. */
    public static function storeAvatar(User $user, UploadedFile $file): self
    {
        static::removeAvatar($user);

        return $user->avatar()->create([
            'name' => static::avatarNameFor($user),
            'path' => $file->store('avatars', 'public'),
            'disk' => 'public',
        ]);
    }

    public static function removeAvatar(User $user): void
    {
        // Queried fresh rather than via $user->avatar, which may be a stale cached relation.
        if ($avatar = $user->avatar()->first()) {
            Storage::disk($avatar->disk)->delete($avatar->path);
            $avatar->delete();
        }
        $user->unsetRelation('avatar');
    }

    /** URL for a slot: the uploaded image if there is one, otherwise the bundled fallback. */
    public static function urlFor(string $name): string
    {
        // Memoized for the current request only, since a page may ask for the same slot twice.
        $url = once(function () use ($name) {
            try {
                return static::where('name', $name)->first()?->url;
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        });

        return $url ?? asset(self::SLOTS[$name]['fallback'] ?? '');
    }
}
