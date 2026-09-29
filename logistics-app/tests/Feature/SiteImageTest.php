<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SiteImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class SiteImageTest extends TestCase
{
    use MigratesCoreTables;

    public function test_super_admin_upload_is_stored_and_shown_on_the_login_page(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user(Role::SuperAdmin))
            ->post('/site-images', [
                'name' => 'login_background_portrait',
                'image' => UploadedFile::fake()->image('bg.jpg', 800, 1200),
            ])->assertRedirect();

        $image = SiteImage::where('name', 'login_background_portrait')->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame(Storage::disk('public')->url($image->path), $image->url);

        auth()->logout();
        $this->get('/login')->assertSee($image->url, false);
    }

    public function test_replacing_an_image_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->user(Role::SuperAdmin);

        $this->actingAs($admin)->post('/site-images', ['name' => 'login_background_portrait', 'image' => UploadedFile::fake()->image('a.jpg', 800, 800)]);
        $old = SiteImage::first()->path;
        $this->actingAs($admin)->post('/site-images', ['name' => 'login_background_portrait', 'image' => UploadedFile::fake()->image('b.jpg', 800, 800)]);

        Storage::disk('public')->assertMissing($old);
        $this->assertSame(1, SiteImage::count());
    }

    public function test_rejects_non_images_unknown_slots_and_other_roles(): void
    {
        Storage::fake('public');
        $admin = $this->user(Role::SuperAdmin);

        $this->actingAs($admin)->post('/site-images', ['name' => 'login_background_portrait', 'image' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors('image');
        $this->actingAs($admin)->post('/site-images', ['name' => 'anything', 'image' => UploadedFile::fake()->image('a.jpg', 800, 800)])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->user(Role::Manager))->post('/site-images', ['name' => 'login_background_portrait', 'image' => UploadedFile::fake()->image('a.jpg', 800, 800)])
            ->assertForbidden();

        $this->assertSame(0, SiteImage::count());
    }

    public function test_only_super_admin_can_open_or_upload_on_the_site_images_page(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user(Role::SuperAdmin))->get('/site-images')->assertOk()->assertSee('Site Images');

        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/site-images')->assertForbidden();
            $this->actingAs($user)->post('/site-images', ['name' => 'login_background_portrait', 'image' => UploadedFile::fake()->image('a.jpg', 800, 800)])
                ->assertForbidden();
        }

        auth()->logout();
        $this->get('/site-images')->assertRedirect('/login');
        $this->assertSame(0, SiteImage::count());
    }

    public function test_profile_photo_is_stored_replaced_and_removed_via_site_images(): void
    {
        Storage::fake('public');
        $user = $this->user(Role::FieldPersonnel);
        $profile = fn (array $extra = []) => $this->actingAs($user)
            ->put('/profile', ['name' => $user->name, 'email' => $user->email] + $extra);

        $profile(['avatar' => UploadedFile::fake()->image('me.jpg', 200, 200)]);
        $first = $user->fresh()->avatar;
        $this->assertSame(SiteImage::avatarNameFor($user), $first->name);
        Storage::disk('public')->assertExists($first->path);
        $this->assertSame($first->url, $user->fresh()->avatarUrl());

        $profile(['avatar' => UploadedFile::fake()->image('me2.jpg', 200, 200)]);
        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame(1, SiteImage::where('user_id', $user->id)->count());

        $second = $user->fresh()->avatar;
        $profile(['remove_avatar' => 1]);
        Storage::disk('public')->assertMissing($second->path);
        $this->assertNull($user->fresh()->avatarUrl());
    }

    public function test_super_admin_manages_any_users_profile_picture_from_site_images(): void
    {
        Storage::fake('public');
        $admin = $this->user(Role::SuperAdmin);
        $target = $this->user(Role::FieldPersonnel);

        $this->actingAs($admin)->post("/site-images/avatars/{$target->id}", ['avatar' => UploadedFile::fake()->image('p.jpg', 200, 200)])
            ->assertRedirect()->assertSessionHas('status');
        $avatar = $target->fresh()->avatar;
        Storage::disk('public')->assertExists($avatar->path);

        $this->actingAs($admin)->get('/site-images')->assertSee('Profile pictures')->assertSee($target->name)->assertSee($avatar->url, false);

        $this->actingAs($admin)->delete("/site-images/avatars/{$target->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($avatar->path);
        $this->assertNull($target->fresh()->avatar);

        $this->actingAs($admin)->post("/site-images/avatars/{$target->id}", ['avatar' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_other_roles_cannot_change_someone_elses_profile_picture(): void
    {
        Storage::fake('public');
        $target = $this->user(Role::FieldPersonnel);

        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->post("/site-images/avatars/{$target->id}", ['avatar' => UploadedFile::fake()->image('p.jpg', 200, 200)])->assertForbidden();
            $this->actingAs($user)->delete("/site-images/avatars/{$target->id}")->assertForbidden();
        }

        $this->assertSame(0, SiteImage::count());
    }

    public function test_falls_back_to_the_bundled_image_when_none_uploaded(): void
    {
        $this->get('/login')->assertSee('images/logistics-bg-portrait.jpg', false);
    }
}
