<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
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
            ->assertSee('Needs attention')->assertSee('+ New shipment')->assertSee('Export CSV')->assertSee('Last 30 days');

        $attention = $response->viewData('attention')->pluck('tracking_number')->all();
        $this->assertEqualsCanonicalizing(['SH-LATE', 'SH-OVERDUE'], $attention);
        $this->assertStringNotContainsString('b-b-', $response->getContent(), 'status badges get a real colour class');

        $this->assertCount(30, $this->get('/dashboard?range=30')->viewData('weekly')['labels']);
        $this->assertCount(7, $this->get('/dashboard?range=999')->viewData('weekly')['labels'], 'unknown periods fall back to 7 days');
    }

    public function test_managers_can_export_shipments_as_csv_but_drivers_cannot(): void
    {
        $this->shipment('SH-EXPORT-1', 'in_transit', ['destination_name' => '=HYPERLINK("x")']);

        $csv = $this->actingAs($this->manager)->get('/dashboard/export?range=30')->assertOk()->streamedContent();
        $this->assertStringContainsString('SH-EXPORT-1', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'formulas are neutralised');
        $this->assertDatabaseHas('activity_logs', ['action' => 'report']);

        $this->actingAs($this->field)->get('/dashboard/export')->assertForbidden();
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

        $this->assertSame(4, substr_count($html, 'class="nav-label nav-toggle"'));
        $this->assertMatchesRegularExpression('/nav-group current" data-group="admin"/', $html, 'the section with the current page is marked open');
        $this->assertStringContainsString('aria-current="page"', $html);

        // One section only (Field Personnel): no toggle needed.
        $this->assertStringContainsString('nav-toggle" aria-expanded="true" aria-controls="nav-operations"  hidden', $this->actingAs($this->field)->get('/shipments')->getContent());
    }
}
