<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use MigratesCoreTables;

    private function attempt(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['email' => $email, 'password' => $password, 'terms' => '1']);
    }

    public function test_an_account_is_locked_for_a_minute_after_five_wrong_passwords(): void
    {
        $user = $this->user(Role::Manager);

        foreach (range(1, 5) as $i) {
            $this->attempt($user->email, 'wrong-password')->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        }

        // Even the right password is refused while locked.
        $this->attempt($user->email, 'password')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
        $this->assertTrue(ActivityLog::where('action', 'login_locked')->exists());

        $this->travel(61)->seconds();
        $this->attempt($user->email, 'password')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_deactivated_account_is_told_so_but_only_with_the_right_password(): void
    {
        $user = $this->user(Role::FieldPersonnel);
        $user->forceFill(['is_active' => false])->save();

        $this->attempt($user->email, 'password')
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Please contact your administrator.']);
        $this->assertGuest();
        // As in the browser: submitted from the login page, which it returns to with the message.
        $this->from('/login')->followingRedirects()
            ->post('/login', ['email' => $user->email, 'password' => 'password', 'terms' => '1'])
            ->assertOk()->assertSee('Your account has been deactivated. Please contact your administrator.');

        // A wrong password gets the usual message, so nobody can probe which accounts are deactivated.
        $this->attempt($user->email, 'wrong-password')->assertSessionHasErrors(['email' => 'Invalid email or password.']);
    }

    public function test_forgot_password_explains_who_resets_passwords_and_no_demo_login_is_shown(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('Ask a <strong>Super Admin</strong> to set a new one', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('admin@logistics.test');
    }

    public function test_the_login_page_can_be_switched_to_tagalog_before_signing_in(): void
    {
        $this->get('/login')->assertSee('Welcome back')->assertSee('form="langForm"', false);

        $this->from('/login')->post('/locale', ['locale' => 'tl'])->assertRedirect('/login');
        $this->get('/login')->assertSee('Maligayang pagbabalik')->assertSee('Mag-sign in');

        // Errors come back in Tagalog too.
        $this->from('/login')->followingRedirects()
            ->post('/login', ['email' => 'nobody@logistics.test', 'password' => 'x', 'terms' => '1'])
            ->assertSee('Mali ang email o password.');

        $this->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_a_successful_sign_in_resets_the_count(): void
    {
        $user = $this->user(Role::Manager);

        foreach (range(1, 4) as $i) {
            $this->attempt($user->email, 'wrong-password');
        }
        $this->attempt($user->email, 'password')->assertRedirect('/dashboard');
        auth()->logout();

        foreach (range(1, 4) as $i) {
            $this->attempt($user->email, 'wrong-password')->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        }
    }

    public function test_one_address_trying_many_accounts_is_blocked(): void
    {
        foreach (range(1, 20) as $i) {
            $this->attempt("nobody{$i}@logistics.test", 'guess');
        }

        $this->attempt('someone-else@logistics.test', 'guess')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));

        // A different address is unaffected.
        $user = $this->user(Role::Manager);
        $this->attempt($user->email, 'password', '10.0.0.2')->assertRedirect('/dashboard');
    }

    public function test_a_password_reset_by_a_super_admin_signs_the_user_out_elsewhere(): void
    {
        $target = $this->user(Role::SuperAdmin);
        $admin = $this->user(Role::SuperAdmin);

        // The target is signed in; the session remembers their current password.
        $this->actingAs($target)->get('/users')->assertOk();

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email,
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasNoErrors();

        // Back in the target's session, with their account as it now is in the database.
        $this->actingAs($target->fresh())->get('/users')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_changing_your_own_password_keeps_you_signed_in(): void
    {
        $admin = $this->user(Role::SuperAdmin);

        $this->actingAs($admin)->get('/users')->assertOk();
        $this->put("/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin->fresh())->get('/users')->assertOk();
    }
}
