<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class FieldDashboardTest extends TestCase
{
    use MigratesCoreTables;

    private User $field;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // The real fleet and shipment tables come from SQL Server-only migrations.
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('tracking_number');
            $t->string('status');
            $t->string('origin_city')->nullable();
            $t->string('destination_name')->nullable();
            $t->string('destination_city')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->dateTime('actual_delivery_at')->nullable();
            $t->string('delivery_result')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
        });
        (require base_path('database/migrations/2026_09_24_000002_create_vehicle_location_pings_table.php'))->up();
        (require base_path('database/migrations/2026_09_29_000003_create_site_contents_table.php'))->up();

        $this->field = $this->user(Role::FieldPersonnel);
        $this->field->forceFill(['name' => 'Felix Field'])->save();
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id]);
    }

    private function shipment(string $tracking, string $status, ?int $driverId = null, array $extra = []): Shipment
    {
        return Shipment::forceCreate($extra + [
            'tracking_number' => $tracking, 'status' => $status, 'origin_city' => 'Manila',
            'destination_name' => "Store {$tracking}", 'destination_city' => 'Batangas City',
            'driver_id' => $driverId ?? $this->driver->id, 'scheduled_delivery_at' => now()->addHours(2),
        ]);
    }

    public function test_a_driver_sees_their_own_dashboard_not_company_analytics(): void
    {
        $this->shipment('SH-MINE-1', 'in_transit');
        $this->shipment('SH-MINE-2', 'pending', null, ['scheduled_delivery_at' => now()->addDay()]);
        $other = Driver::create(['name' => 'Someone Else']);
        $this->shipment('SH-NOT-MINE', 'in_transit', $other->id);

        $this->actingAs($this->field)->get('/dashboard')->assertOk()
            ->assertViewIs('dashboard-field')
            ->assertSee('Felix!')
            ->assertSee('Next delivery')
            ->assertSee('SH-MINE-1')
            ->assertSee('SH-MINE-2')
            ->assertDontSee('SH-NOT-MINE')
            ->assertDontSee('Low stock items')
            ->assertDontSee('Deliveries this week')
            ->assertSee('How to use this app')
            ->assertSee('call 911 first', false);
    }

    public function test_a_delayed_delivery_comes_first(): void
    {
        $this->shipment('SH-SOON', 'in_transit', null, ['scheduled_delivery_at' => now()->addHour()]);
        $this->shipment('SH-LATE', 'delayed', null, ['scheduled_delivery_at' => now()->addHours(5)]);

        $next = $this->actingAs($this->field)->get('/dashboard')->viewData('next');
        $this->assertSame('SH-LATE', $next->tracking_number);
    }

    public function test_unread_alerts_are_mentioned(): void
    {
        Alert::create(['user_id' => $this->field->id, 'type' => 'note', 'message' => 'Hi']);

        $this->actingAs($this->field)->get('/dashboard')->assertSee('unread alert from the office', false);
    }

    public function test_a_helper_gets_the_helper_version(): void
    {
        $helper = $this->user(Role::FieldPersonnel);
        $helper->forceFill(['field_position' => 'helper'])->save();

        $this->actingAs($helper)->get('/dashboard')->assertOk()
            ->assertViewIs('dashboard-field')
            ->assertSee("once you're assigned to a truck");
    }

    public function test_super_admin_edits_the_sections_drivers_see(): void
    {
        $admin = $this->user(Role::SuperAdmin);
        $section = SiteContent::where('key', 'company')->firstOrFail();

        $this->actingAs($admin)->get('/driver-dashboard')->assertOk()->assertSee('About the company');
        $this->put("/driver-dashboard/{$section->id}", ['title' => 'About Us', 'body' => "We deliver across Luzon.\n\n- Safe\n- On time\n\n<script>x</script>"])
            ->assertSessionHasNoErrors();

        $html = $this->actingAs($this->field)->get('/dashboard')->assertSee('About Us')->getContent();
        $this->assertStringContainsString('<li>Safe</li><li>On time</li>', str_replace("\n", '', $html));
        $this->assertStringNotContainsString('<script>x</script>', $html, 'text is shown safely, not as HTML');
        $this->assertSame($admin->id, $section->fresh()->updated_by);
    }

    public function test_the_greeting_and_page_follow_the_chosen_language(): void
    {
        $this->travelTo(today()->setTime(9, 0));
        $this->shipment('SH-LANG-1', 'in_transit');

        $this->actingAs($this->field)->get('/dashboard')
            ->assertSee('Good morning, Felix!')->assertSee('Next delivery')->assertSee('In transit');

        $this->post('/locale', ['locale' => 'tl'])->assertRedirect();
        $this->assertSame('tl', $this->field->fresh()->locale, 'saved on the account');

        $this->actingAs($this->field->fresh())->get('/dashboard')
            ->assertSee('Magandang umaga, Felix!')
            ->assertSee('Susunod na delivery')
            ->assertSee('Nasa biyahe')
            ->assertSee('Mga shipment')          // the menu
            ->assertSee('Field Personnel · Driver') // role stays readable in both
            ->assertDontSee('Good morning');

        $this->travelTo(today()->setTime(15, 0));
        $this->get('/dashboard')->assertSee('Magandang hapon');
        $this->travelTo(today()->setTime(20, 0));
        $this->get('/dashboard')->assertSee('Magandang gabi');
    }

    public function test_only_super_admins_edit_the_sections(): void
    {
        $section = SiteContent::firstOrFail();

        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/driver-dashboard')->assertForbidden();
            $this->actingAs($user)->put("/driver-dashboard/{$section->id}", ['title' => 'X', 'body' => 'Y'])->assertForbidden();
        }
    }
}
