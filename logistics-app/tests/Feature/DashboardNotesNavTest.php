<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\Helper;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class DashboardNotesNavTest extends TestCase
{
    use MigratesCoreTables;

    private User $manager;

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
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('helper_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->text('notes')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->dateTime('dispatched_at')->nullable();
            $t->dateTime('actual_delivery_at')->nullable();
            $t->string('delivery_result')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id('shipment_item_id');
            $t->unsignedBigInteger('shipment_id');
            $t->integer('quantity');
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
        });

        $this->manager = $this->user(Role::Manager);
        $this->field = $this->user(Role::FieldPersonnel);
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id]);
    }

    private function shipment(string $tracking, string $status, array $extra = []): Shipment
    {
        return Shipment::forceCreate($extra + [
            'tracking_number' => $tracking, 'status' => $status, 'origin_city' => 'Manila',
            'destination_name' => 'Branch', 'destination_city' => 'Pasig', 'driver_id' => $this->driver->id,
            'created_by' => $this->manager->id, 'scheduled_delivery_at' => now()->addHours(3),
        ]);
    }

    // ---- Management dashboard ----

    public function test_the_management_dashboard_shows_sections_actions_and_what_needs_attention(): void
    {
        $this->shipment('SH-ONTRACK', 'in_transit');
        $this->shipment('SH-LATE', 'delayed');
        $this->shipment('SH-OVERDUE', 'pending', ['scheduled_delivery_at' => now()->subHour()]);
        $this->shipment('SH-DONE', 'delivered', ['actual_delivery_at' => now()->subDay(), 'delivery_result' => 'on_time']);

        $response = $this->actingAs($this->manager)->get('/dashboard')->assertOk()->assertViewIs('dashboard')
            ->assertSee('Needs attention')->assertSee('+ New shipment')->assertDontSee('Export CSV')->assertSee('Last 30 days');

        $attention = $response->viewData('attention')->pluck('tracking_number')->all();
        $this->assertEqualsCanonicalizing(['SH-LATE', 'SH-OVERDUE'], $attention);
        $this->assertStringNotContainsString('b-b-', $response->getContent(), 'status badges get a real colour class');

        $this->assertCount(30, $this->get('/dashboard?range=30')->viewData('weekly')['labels']);
        $this->assertCount(7, $this->get('/dashboard?range=999')->viewData('weekly')['labels'], 'unknown periods fall back to 7 days');
    }

    public function test_the_dashboard_no_longer_exports_shipments(): void
    {
        $this->actingAs($this->manager)->get('/dashboard')->assertOk()->assertDontSee('Export CSV');
        $this->actingAs($this->manager)->get('/dashboard/export?range=30')->assertNotFound();
    }

    // ---- Notes: exact times and live updates ----

    public function test_notes_show_their_exact_time_and_can_be_posted_without_a_reload(): void
    {
        $s = $this->shipment('SH-NOTES', 'in_transit');
        $this->travelTo(now()->setTime(16, 6));

        $json = $this->actingAs($this->field)->postJson("/shipments/{$s->shipment_id}/notes", ['body' => 'Nasa NLEX na po'])
            ->assertCreated()->json();

        $this->assertStringContainsString('Nasa NLEX na po', $json['html']);
        $this->assertStringContainsString('4:06 PM', $json['html'], 'exact time shown');
        $this->assertStringContainsString('datetime="'.now()->toIso8601String().'"', $json['html'], 'server time recorded');
        $this->assertSame(now()->toDateTimeString(), $s->shipmentNotes()->first()->created_at->toDateTimeString());

        $this->postJson("/shipments/{$s->shipment_id}/notes", ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_the_feed_returns_only_newer_notes_for_other_viewers(): void
    {
        $s = $this->shipment('SH-FEED', 'in_transit');
        $first = $s->shipmentNotes()->create(['user_id' => $this->manager->id, 'body' => 'san kna?']);
        $second = $s->shipmentNotes()->create(['user_id' => $this->field->id, 'body' => 'malapit na po']);

        $feed = $this->actingAs($this->manager)->getJson("/shipments/{$s->shipment_id}/notes?after={$first->id}")->assertOk()->json();

        $this->assertStringContainsString('malapit na po', $feed['html']);
        $this->assertStringNotContainsString('san kna?', $feed['html']);
        $this->assertSame($second->id, $feed['last_id']);

        $none = $this->getJson("/shipments/{$s->shipment_id}/notes?after={$second->id}")->json();
        $this->assertSame('', $none['html']);
    }

    // ---- Collapsible sidebar ----

    public function test_the_sidebar_is_grouped_into_collapsible_sections(): void
    {
        $html = $this->actingAs($this->user(Role::SuperAdmin))->get('/users')->getContent();

        // Super Admin: Insights and Administration.
        $this->assertSame(2, substr_count($html, 'class="nav-label nav-toggle"'));
        $this->assertMatchesRegularExpression('/nav-group current" data-group="admin"/', $html, 'the section with the current page is marked open');
        $this->assertStringContainsString('aria-current="page"', $html);

        // One section only (Field Personnel): no toggle needed.
        $this->assertStringContainsString('nav-toggle" aria-expanded="true" aria-controls="nav-operations"  hidden', $this->actingAs($this->field)->get('/shipments')->getContent());
    }

    public function test_each_role_gets_only_its_own_menu_sections(): void
    {
        $groups = function (User $user, string $page) {
            preg_match_all('/data-group="([a-z]+)"/', $this->actingAs($user)->get($page)->assertOk()->getContent(), $m);

            return array_values(array_unique($m[1]));
        };

        $this->assertSame(['insights', 'admin'], $groups($this->user(Role::SuperAdmin), '/users'));
        $this->assertSame(['operations', 'fleet', 'insights', 'admin'], $groups($this->user(Role::Manager), '/shipments'));
        $this->assertSame(['operations', 'fleet'], $groups($this->user(Role::LogisticsCoordinator), '/shipments'));
        $this->assertSame(['operations'], $groups($this->field, '/shipments'));

        // Driver dashboard and Site Images are the Administration pages a Manager doesn't get.
        $this->actingAs($this->user(Role::Manager))->get('/users')->assertOk()
            ->assertSee('Users &amp; Roles', false)->assertSee('Emergency contacts')->assertSee('Reports')
            ->assertDontSee('Driver dashboard')->assertDontSee('Site Images')
            ->assertSee('id="bellMenu"', false);
        // A Coordinator has no Administration or Insights pages at all.
        $this->actingAs($this->user(Role::LogisticsCoordinator))->get('/shipments')->assertOk()
            ->assertDontSee('Users &amp; Roles', false)->assertDontSee('Emergency contacts')->assertDontSee('Reports');
        // No alert bell for a Super Admin.
        $this->actingAs($this->user(Role::SuperAdmin))->get('/users')->assertOk()
            ->assertSee('Driver dashboard')->assertSee('Site Images')->assertDontSee('id="bellMenu"', false);
    }

    public function test_managers_read_the_activity_log_but_cannot_print_or_export_it(): void
    {
        $this->actingAs($this->manager)->get('/reports/activity-logs')->assertOk()
            ->assertSee('Entries can', false)->assertDontSee('Export CSV')->assertDontSee('/reports/activity-logs/print', false);
        $this->actingAs($this->user(Role::SuperAdmin))->get('/reports/activity-logs')->assertOk()
            ->assertSee('Export CSV')->assertSee('/reports/activity-logs/print', false);

        // Printing and exporting stay with the Super Admin, even by typing the address.
        foreach (['/print', '/export'] as $page) {
            $this->actingAs($this->manager)->get("/reports/activity-logs{$page}")->assertForbidden();
            $this->actingAs($this->user(Role::SuperAdmin))->get("/reports/activity-logs{$page}")->assertOk();
        }

        foreach ([Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            foreach (['', '/print', '/export'] as $page) {
                $this->actingAs($this->user($role))->get("/reports/activity-logs{$page}")->assertForbidden();
            }
        }
    }

    public function test_coordinators_have_no_administration_pages(): void
    {
        $coordinator = $this->user(Role::LogisticsCoordinator);

        foreach (['/users', '/users/create', "/users/{$this->field->id}/edit", '/emergency-contacts', '/driver-dashboard', '/site-images'] as $page) {
            $this->actingAs($coordinator)->get($page)->assertForbidden();
        }
        $this->actingAs($coordinator)->post('/users', ['name' => 'X', 'email' => 'x@logistics.test'])->assertForbidden();
        $this->actingAs($coordinator)->put("/users/{$this->field->id}", ['name' => 'X', 'email' => 'x@logistics.test'])->assertForbidden();
        $this->actingAs($coordinator)->patch("/users/{$this->field->id}/role", ['role' => 'manager'])->assertForbidden();
        $this->actingAs($coordinator)->patch("/users/{$this->field->id}/position", ['field_position' => 'helper'])->assertForbidden();
        $this->actingAs($coordinator)->patch("/users/{$this->field->id}/active")->assertForbidden();
        $this->assertSame(Role::FieldPersonnel, $this->field->fresh()->role);
        $this->assertTrue((bool) $this->field->fresh()->is_active);
    }

    public function test_field_personnel_see_only_the_shipments_assigned_to_them(): void
    {
        $mine = $this->shipment('SH-MINE-1', 'in_transit');
        $other = Driver::create(['name' => 'Someone Else']);
        $theirs = $this->shipment('SH-THEIRS-1', 'in_transit', ['driver_id' => $other->id]);

        $this->actingAs($this->field)->get('/shipments')->assertOk()->assertSee('SH-MINE-1')->assertDontSee('SH-THEIRS-1');
        $this->actingAs($this->field)->get('/shipments?q=THEIRS')->assertOk()->assertDontSee('SH-THEIRS-1');
        $this->actingAs($this->field)->get('/shipments/print')->assertOk()->assertSee('SH-MINE-1')->assertDontSee('SH-THEIRS-1');

        $this->actingAs($this->field)->get("/shipments/{$mine->shipment_id}/notes")->assertOk();
        $this->assertTrue($mine->isVisibleTo($this->field));
        foreach (['', '/print', '/notes'] as $page) {
            $this->actingAs($this->field)->get("/shipments/{$theirs->shipment_id}{$page}")->assertForbidden();
        }

        // An account with no driver or helper record sees none.
        $this->actingAs($this->user(Role::FieldPersonnel))->get('/shipments')->assertOk()->assertDontSee('SH-MINE-1')->assertDontSee('SH-THEIRS-1');
        // The office still sees every shipment.
        $this->actingAs($this->manager)->get('/shipments')->assertOk()->assertSee('SH-MINE-1')->assertSee('SH-THEIRS-1');
        $this->actingAs($this->manager)->get("/shipments/{$theirs->shipment_id}/notes")->assertOk();
    }

    public function test_a_helper_sees_only_the_shipments_they_ride_along_on(): void
    {
        $account = $this->user(Role::FieldPersonnel);
        $helper = Helper::create(['name' => 'Hector Helper', 'user_id' => $account->id]);
        $other = Driver::create(['name' => 'Someone Else']);
        $riding = $this->shipment('SH-RIDING-1', 'in_transit', ['driver_id' => $other->id, 'helper_id' => $helper->id]);
        $notRiding = $this->shipment('SH-NOTMINE-1', 'in_transit', ['driver_id' => $other->id]);

        foreach (['/shipments', '/shipments/print'] as $page) {
            $this->actingAs($account)->get($page)->assertOk()->assertSee('SH-RIDING-1')->assertDontSee('SH-NOTMINE-1');
        }
        $this->actingAs($account)->get("/shipments/{$riding->shipment_id}/notes")->assertOk();
        $this->assertTrue($riding->isVisibleTo($account));
        foreach (['', '/print', '/notes'] as $page) {
            $this->actingAs($account)->get("/shipments/{$notRiding->shipment_id}{$page}")->assertForbidden();
        }

        // The driver of another shipment doesn't see the helper's one.
        $this->actingAs($this->field)->get('/shipments')->assertOk()->assertDontSee('SH-RIDING-1');
    }

    public function test_a_super_admin_is_kept_out_of_operations_and_fleet(): void
    {
        $admin = $this->user(Role::SuperAdmin);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/reports/activity-logs');
        foreach (['/shipments', '/shipments/create', '/calendar', '/tracking', '/vehicles', '/drivers'] as $page) {
            $this->actingAs($admin)->get($page)->assertForbidden();
        }
        foreach (['/reports/activity-logs', '/users', '/emergency-contacts'] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        // Driver dashboard and Site Images are the Super Admin's alone, and so are printing
        // and exporting the activity log.
        foreach ([Role::Manager, Role::LogisticsCoordinator, Role::FieldPersonnel] as $role) {
            $this->actingAs($this->user($role))->get('/reports/activity-logs/print')->assertForbidden();
            $this->actingAs($this->user($role))->get('/reports/activity-logs/export')->assertForbidden();
            $this->actingAs($this->user($role))->get('/site-images')->assertForbidden();
            $this->actingAs($this->user($role))->get('/driver-dashboard')->assertForbidden();
        }
    }
}
