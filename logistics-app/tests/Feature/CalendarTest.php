<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\Shipment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use MigratesCoreTables;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 09:00:00');

        // The real tables come from SQL Server-only migrations; create the columns used here.
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique();
            $t->string('name');
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->string('status')->default('available');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('tracking_number');
            $t->string('status');
            $t->string('origin_name')->nullable();
            $t->string('origin_address')->nullable();
            $t->string('origin_city')->nullable();
            $t->string('destination_name')->nullable();
            $t->string('destination_address')->nullable();
            $t->string('destination_city')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->dateTime('scheduled_pickup_at')->nullable();
            $t->dateTime('dispatched_at')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->dateTime('actual_delivery_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->integer('sequence')->default(1);
        });
    }

    private function shipment(string $tracking, array $attributes = []): Shipment
    {
        return Shipment::forceCreate($attributes + [
            'tracking_number' => $tracking,
            'status' => 'pending',
            'origin_name' => 'Main warehouse',
            'origin_city' => 'Manila',
            'destination_name' => 'Branch',
            'destination_city' => 'Batangas City',
            'scheduled_delivery_at' => '2026-10-12 14:00:00',
        ]);
    }

    public function test_a_delivery_shows_on_its_scheduled_day_and_a_pickup_on_its_own(): void
    {
        $this->shipment('SH-CAL-1', ['scheduled_pickup_at' => '2026-10-10 08:00:00']);
        $office = $this->user(Role::LogisticsCoordinator);

        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-12')
            ->assertOk()->assertSee('October 2026')->assertSee('SH-CAL-1')->assertSee('Batangas City');

        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-10')
            ->assertOk()->assertSee('SH-CAL-1')->assertSee('Main warehouse');

        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-11')
            ->assertOk()->assertSee('No pickups or deliveries on this day.')
            // Still on its own days in the grid, where each entry carries the tracking number.
            ->assertSee('<strong>SH-CAL-1</strong>', false);
    }

    public function test_without_a_scheduled_pickup_the_dispatch_time_is_used(): void
    {
        $this->shipment('SH-CAL-2', ['status' => 'in_transit', 'dispatched_at' => '2026-10-07 10:30:00']);

        $this->actingAs($this->user(Role::Manager))->get('/calendar?date=2026-10-01&day=2026-10-07')
            ->assertOk()->assertSee('SH-CAL-2')->assertSee('Dispatched');
    }

    public function test_a_delivery_past_its_eta_is_marked_overdue_until_delivered(): void
    {
        $this->shipment('SH-LATE', ['status' => 'in_transit', 'scheduled_delivery_at' => '2026-10-03 12:00:00']);
        $this->shipment('SH-DONE', ['status' => 'delivered', 'scheduled_delivery_at' => '2026-10-02 12:00:00']);
        $office = $this->user(Role::Manager);

        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-03')->assertSee('SH-LATE')->assertSee('b-delayed">Overdue', false);
        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-02')->assertSee('SH-DONE')->assertDontSee('b-delayed">Overdue', false);
    }

    public function test_field_personnel_see_only_their_own_shipments(): void
    {
        $field = $this->user(Role::FieldPersonnel);
        $mine = Driver::create(['name' => 'Felix Field', 'user_id' => $field->id]);
        $other = Driver::create(['name' => 'Olga Other']);
        $this->shipment('SH-MINE', ['driver_id' => $mine->id]);
        $this->shipment('SH-THEIRS', ['driver_id' => $other->id]);

        // Asking for another driver through the filter must not widen what they see.
        $this->actingAs($field)->get("/calendar?date=2026-10-01&day=2026-10-12&driver_id={$other->id}")
            ->assertOk()->assertSee('SH-MINE')->assertDontSee('SH-THEIRS')->assertDontSee('All drivers');
    }

    public function test_field_personnel_not_linked_to_a_driver_see_nothing(): void
    {
        $this->shipment('SH-ANY');

        $this->actingAs($this->user(Role::FieldPersonnel))->get('/calendar?date=2026-10-01&day=2026-10-12')
            ->assertOk()->assertDontSee('SH-ANY')->assertSee('linked to a driver yet');
    }

    public function test_office_staff_can_filter_by_driver_and_status(): void
    {
        $a = Driver::create(['name' => 'Ana']);
        $b = Driver::create(['name' => 'Ben']);
        $this->shipment('SH-ANA', ['driver_id' => $a->id]);
        $this->shipment('SH-BEN', ['driver_id' => $b->id, 'status' => 'cancelled']);
        $office = $this->user(Role::LogisticsCoordinator);

        $this->actingAs($office)->get("/calendar?date=2026-10-01&day=2026-10-12&driver_id={$a->id}")->assertSee('SH-ANA')->assertDontSee('SH-BEN');
        $this->actingAs($office)->get('/calendar?date=2026-10-01&day=2026-10-12&status=cancelled')->assertSee('SH-BEN')->assertDontSee('SH-ANA');
    }

    public function test_week_view_covers_only_that_week_and_bad_dates_fall_back_to_today(): void
    {
        $this->shipment('SH-NEXT-WEEK');
        $office = $this->user(Role::Manager);

        $this->actingAs($office)->get('/calendar?view=week&date=2026-10-05')->assertOk()->assertSee('Oct 4 – Oct 10, 2026')->assertDontSee('SH-NEXT-WEEK');
        $this->actingAs($office)->get('/calendar?view=week&date=2026-10-12&day=2026-10-12')->assertSee('SH-NEXT-WEEK');
        $this->actingAs($office)->get('/calendar?date=not-a-date&day=2026-99-99')->assertOk()->assertSee('October 2026');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/calendar')->assertRedirect('/login');
    }
}
