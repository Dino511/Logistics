<?php

namespace Tests\Feature;

use App\Enums\Role;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class InstallableAppTest extends TestCase
{
    use MigratesCoreTables;

    public function test_the_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path($icon['src']));
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }
    }

    public function test_the_service_worker_and_its_offline_page_exist(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/offline.html'", $worker);
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_sign_in_and_signed_in_pages_link_the_manifest_and_register_the_worker(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false)->assertSee("register('/sw.js')", false);

        $this->actingAs($this->user(Role::Manager))->get('/emergency-contacts')->assertOk()
            ->assertSee('rel="manifest"', false)->assertSee("register('/sw.js')", false);
    }
}
