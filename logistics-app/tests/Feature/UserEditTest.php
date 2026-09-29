<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class UserEditTest extends TestCase
{
    use MigratesCoreTables;

    public function test_super_admin_can_change_name_email_and_password(): void
    {
        $target = $this->user(Role::FieldPersonnel);
        $oldToken = $target->remember_token;

        $this->actingAs($this->user(Role::SuperAdmin))->get("/users/{$target->id}/edit")->assertOk()->assertSee($target->email);

        $this->put("/users/{$target->id}", [
            'name' => 'Renamed', 'email' => 'new@logistics.test', 'field_position' => 'driver',
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(['Renamed', 'new@logistics.test'], [$target->name, $target->email]);
        $this->assertTrue(Hash::check('NewPass123', $target->password));
        $this->assertNotSame($oldToken, $target->remember_token);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'NewPass123']);
    }

    public function test_blank_password_keeps_the_current_one(): void
    {
        $target = $this->user(Role::Manager);
        $hash = $target->password;

        $this->actingAs($this->user(Role::SuperAdmin))
            ->put("/users/{$target->id}", ['name' => 'Maria', 'email' => $target->email, 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame($hash, $target->fresh()->password);
    }

    public function test_rejects_weak_mismatched_or_duplicate_values(): void
    {
        $admin = $this->user(Role::SuperAdmin);
        $target = $this->user(Role::Manager);
        $base = ['name' => 'X', 'email' => $target->email];

        $this->actingAs($admin)->put("/users/{$target->id}", $base + ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->put("/users/{$target->id}", $base + ['password' => 'NewPass123', 'password_confirmation' => 'Other123'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->put("/users/{$target->id}", ['name' => 'X', 'email' => $admin->email])->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_add_a_user_who_can_then_sign_in(): void
    {
        $this->actingAs($this->user(Role::SuperAdmin))->get('/users/create')->assertOk()->assertSee('Add user');

        $this->post('/users', [
            'name' => 'Nina Driver', 'email' => 'nina@logistics.test', 'role' => Role::FieldPersonnel->value, 'field_position' => 'driver',
            'password' => 'Welcome123', 'password_confirmation' => 'Welcome123',
        ])->assertRedirect('/users')->assertSessionHasNoErrors();

        $new = User::where('email', 'nina@logistics.test')->firstOrFail();
        $this->assertSame(Role::FieldPersonnel, $new->role);
        $this->assertTrue((bool) $new->is_active);
        $this->assertTrue(Hash::check('Welcome123', $new->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_created']);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'Welcome123']);

        auth()->logout();
        $this->post('/login', ['email' => 'nina@logistics.test', 'password' => 'Welcome123', 'terms' => '1'])->assertRedirect('/dashboard');
    }

    public function test_adding_a_user_rejects_bad_input(): void
    {
        $admin = $this->user(Role::SuperAdmin);
        $good = ['name' => 'X', 'email' => 'x@logistics.test', 'role' => Role::Manager->value, 'password' => 'Welcome123', 'password_confirmation' => 'Welcome123'];

        $this->actingAs($admin)->post('/users', ['email' => $admin->email] + $good)->assertSessionHasErrors('email');
        $this->actingAs($admin)->post('/users', ['role' => 'owner'] + $good)->assertSessionHasErrors('role');
        $this->actingAs($admin)->post('/users', ['password' => 'short', 'password_confirmation' => 'short'] + $good)->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/users', ['password' => ''] + $good)->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'x@logistics.test']);
    }

    public function test_other_roles_cannot_add_users(): void
    {
        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/users/create')->assertForbidden();
            $this->actingAs($user)->post('/users', [
                'name' => 'Sneaky', 'email' => 'sneaky@logistics.test', 'role' => Role::SuperAdmin->value,
                'password' => 'Welcome123', 'password_confirmation' => 'Welcome123',
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@logistics.test']);
    }

    public function test_field_personnel_are_added_as_driver_or_helper(): void
    {
        $this->actingAs($this->user(Role::SuperAdmin));
        $base = ['password' => 'Welcome123', 'password_confirmation' => 'Welcome123', 'role' => Role::FieldPersonnel->value];

        $this->post('/users', $base + ['name' => 'No Position', 'email' => 'none@logistics.test'])
            ->assertSessionHasErrors(['field_position' => 'Choose whether this Field Personnel is a driver or a helper.']);

        $this->post('/users', $base + ['name' => 'Hana Helper', 'email' => 'helper@logistics.test', 'field_position' => 'helper'])
            ->assertSessionHasNoErrors();
        $helper = User::where('email', 'helper@logistics.test')->firstOrFail();
        $this->assertSame('Field Personnel · Helper', $helper->roleLabel());
        $this->assertTrue($helper->isHelper());

        // Other roles never keep a position, even if one is sent.
        $this->post('/users', ['name' => 'Mo', 'email' => 'mo@logistics.test', 'role' => Role::Manager->value, 'field_position' => 'driver'] + $base)
            ->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'mo@logistics.test')->value('field_position'));
    }

    public function test_position_can_be_changed_and_is_cleared_when_leaving_field_personnel(): void
    {
        // The real drivers table comes from a SQL Server-only migration.
        Schema::create('drivers', function ($t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('name');
            $t->timestamps();
        });
        $this->actingAs($this->user(Role::SuperAdmin));
        $person = $this->user(Role::FieldPersonnel);

        $this->patch("/users/{$person->id}/position", ['field_position' => 'helper'])->assertSessionHasNoErrors();
        $this->assertSame('helper', $person->fresh()->field_position);
        $this->patch("/users/{$person->id}/position", ['field_position' => 'captain'])->assertSessionHasErrors('field_position');

        $this->patch("/users/{$person->id}/role", ['role' => Role::LogisticsCoordinator->value]);
        $this->assertNull($person->fresh()->field_position);
        $this->assertSame('Logistics Coordinator', $person->fresh()->roleLabel());

        // Back to Field Personnel: the position must be chosen again.
        $this->patch("/users/{$person->id}/role", ['role' => Role::FieldPersonnel->value])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Choose whether they are a driver or a helper'));
        $this->assertSame('Field Personnel · Position not set', $person->fresh()->roleLabel());

        // A person linked to a driver record can't become a helper.
        $linked = $this->user(Role::FieldPersonnel);
        Driver::create(['name' => 'Linked', 'user_id' => $linked->id]);
        $this->patch("/users/{$linked->id}/position", ['field_position' => 'helper'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Unlink them on the Drivers page'));
        $this->assertSame('driver', $linked->fresh()->field_position);

        // Only Field Personnel have a position.
        $manager = $this->user(Role::Manager);
        $this->patch("/users/{$manager->id}/position", ['field_position' => 'driver'])->assertStatus(422);
    }

    public function test_the_list_shows_the_position_picker_for_field_personnel_only(): void
    {
        $this->user(Role::FieldPersonnel);
        $this->user(Role::Manager);

        $html = $this->actingAs($this->user(Role::SuperAdmin))->get('/users')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="field_position"'));
        $this->assertStringContainsString('Truck / Cargo Helper', $html);
    }

    public function test_other_roles_cannot_edit_users(): void
    {
        $target = $this->user(Role::FieldPersonnel);
        $hash = $target->password;

        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get("/users/{$target->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/users/{$target->id}", [
                'name' => 'Hacked', 'email' => $target->email, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
            ])->assertForbidden();
        }

        $this->assertSame($hash, $target->fresh()->password);
        $this->assertNotSame('Hacked', $target->fresh()->name);
    }
}
