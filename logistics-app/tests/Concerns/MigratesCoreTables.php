<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;

/**
 * Some migrations are SQL Server-only, so feature tests on the in-memory SQLite
 * database run just the ones covering users, activity logs and site images.
 * Includes RefreshDatabase, so use this instead of it.
 */
trait MigratesCoreTables
{
    use RefreshDatabase;

    /**
     * Each test class builds its own cut-down copies of the SQL Server tables. Eloquent
     * remembers a table's columns for the whole test run, so without this a model would
     * silently drop attributes that an earlier test's smaller table didn't have.
     */
    protected function setUpMigratesCoreTables(): void
    {
        (new ReflectionProperty(Model::class, 'guardableColumns'))->setValue(null, []);
    }

    protected function migrateFreshUsing()
    {
        return ['--path' => array_map(fn ($f) => "database/migrations/$f.php", [
            '0001_01_01_000000_create_users_table',
            '0001_01_01_000001_create_cache_table',
            '2026_09_21_000002_add_role_to_users_table',
            '2026_09_21_000003_add_avatar_path_to_users_table',
            '2026_09_21_000005_add_is_active_to_users_table',
            '2026_09_21_000007_create_activity_logs_table',
            '2026_09_23_000003_create_site_images_table',
            '2026_09_23_000004_move_user_avatars_to_site_images',
            '2026_09_24_000003_create_shipment_notes_and_alerts_tables',
            '2026_09_29_000001_create_emergency_contacts_table',
            '2026_09_29_000002_add_field_position_to_users_table',
            '2026_09_29_000004_add_locale_to_users_table',
            '2026_10_06_000001_create_shipment_pickups_table',
            '2026_10_07_000001_create_helpers_table',
        ])];
    }

    protected function user(Role $role): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role,
            'field_position' => $role === Role::FieldPersonnel ? 'driver' : null,
            'is_active' => true,
        ])->save();

        return $user;
    }
}
