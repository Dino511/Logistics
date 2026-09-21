<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Append-only audit trail. Rows are written by ActivityLog::record() and never edited. */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** action => [label, badge class] */
    public const ACTIONS = [
        'login' => ['Sign in', 'b-delivered'],
        'logout' => ['Sign out', 'b-pending'],
        'login_failed' => ['Failed sign-in', 'b-delayed'],
        'created' => ['Created', 'b-transit'],
        'updated' => ['Updated', 'b-transit'],
        'deleted' => ['Deleted', 'b-delayed'],
        'status_changed' => ['Status change', 'b-transit'],
        'role_changed' => ['Role change', 'b-delayed'],
        'activated' => ['Activated', 'b-delivered'],
        'deactivated' => ['Deactivated', 'b-inactive'],
        'profile_updated' => ['Profile update', 'b-pending'],
        'report' => ['Report', 'b-pending'],
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action][0] ?? ucfirst(str_replace('_', ' ', $action));
    }

    public static function actionBadge(string $action): string
    {
        return self::ACTIONS[$action][1] ?? 'b-pending';
    }

    /**
     * Write one entry. Logging must never break the action being logged, so failures are
     * reported and swallowed. Call this after a database transaction has finished.
     */
    public static function record(string $action, string $description, ?Model $subject = null, ?User $user = null): void
    {
        try {
            $user ??= auth()->user();

            static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Guest',
                'user_role' => $user?->role?->label(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description, 0, 1000),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
