<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VehicleLocationPing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class TrackingTest extends TestCase
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
            $t->string('status')->default('available');
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
            $t->string('destination_name')->nullable();
            $t->string('destination_city')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->integer('sequence')->default(1);
        });
        // The real migration for the new table.
        (require base_path('database/migrations/2026_09_24_000002_create_vehicle_location_pings_table.php'))->up();

        DB::table('vehicles')->insert(['id' => 1, 'plate_number' => 'ABC 1234']);
        $this->field = $this->user(Role::FieldPersonnel);
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id, 'vehicle_id' => 1]);
    }

    private function shipment(string $status = 'in_transit', ?int $driverId = null): Shipment
    {
        return Shipment::forceCreate([
            'tracking_number' => 'SH-T-'.random_int(1000, 9999), 'status' => $status,
            'destination_name' => 'Branch', 'destination_city' => 'Cebu City',
            'driver_id' => $driverId ?? $this->driver->id,
        ]);
    }

    private function ping(Shipment $s, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->field)->postJson("/shipments/{$s->shipment_id}/tracking/ping",
            $extra + ['latitude' => 14.5995, 'longitude' => 120.9842, 'accuracy' => 12.4]);
    }

    public function test_the_assigned_driver_can_send_a_position(): void
    {
        $s = $this->shipment();

        $this->ping($s)->assertOk()->assertJson(['ok' => true]);

        $p = VehicleLocationPing::firstOrFail();
        $this->assertSame([$this->driver->id, $s->shipment_id, 1, 12], [$p->driver_id, $p->shipment_id, $p->vehicle_id, $p->accuracy_m]);
        $this->assertEqualsWithDelta(14.5995, $p->latitude, 0.00001);
    }

    public function test_positions_closer_than_20_seconds_are_skipped(): void
    {
        $s = $this->shipment();

        $this->ping($s)->assertOk();
        $this->ping($s)->assertOk()->assertJson(['skipped' => true]);
        $this->travel(21)->seconds();
        $this->ping($s)->assertOk()->assertJsonMissing(['skipped' => true]);

        $this->assertSame(2, VehicleLocationPing::count());
    }

    public function test_no_positions_for_someone_elses_or_finished_shipments(): void
    {
        $other = Driver::create(['name' => 'Other', 'status' => 'active']);
        $this->ping($this->shipment('in_transit', $other->id))->assertForbidden();
        $this->ping($this->shipment('delivered'))->assertStatus(409);
        $this->ping($this->shipment('pending'))->assertStatus(409);
        $this->ping($this->shipment(), ['latitude' => 200])->assertUnprocessable();

        // Office roles don't send positions.
        $this->ping($this->shipment(), [], $this->user(Role::Manager))->assertForbidden();

        $this->assertSame(0, VehicleLocationPing::count());
    }

    public function test_starting_and_stopping_is_logged(): void
    {
        $s = $this->shipment();

        $this->actingAs($this->field)->postJson("/shipments/{$s->shipment_id}/tracking/sharing", ['action' => 'start'])->assertOk();
        $this->actingAs($this->field)->postJson("/shipments/{$s->shipment_id}/tracking/sharing", ['action' => 'stop'])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'tracking_started', 'user_id' => $this->field->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'tracking_stopped', 'user_id' => $this->field->id]);
    }

    public function test_the_live_map_shows_the_latest_position_of_active_shipments_to_office_roles_only(): void
    {
        $s = $this->shipment();
        $this->ping($s, ['latitude' => 10.0]);
        $this->travel(30)->seconds();
        $this->ping($s, ['latitude' => 10.5]);

        $done = $this->shipment('delivered');
        VehicleLocationPing::create(['driver_id' => $this->driver->id, 'shipment_id' => $done->shipment_id, 'latitude' => 9, 'longitude' => 123, 'recorded_at' => now()]);

        $data = $this->actingAs($this->user(Role::LogisticsCoordinator))->getJson('/tracking/positions')->assertOk()->json();
        $this->assertCount(1, $data, 'one entry per active shipment; delivered ones are left out');
        $this->assertEqualsWithDelta(10.5, $data[0]['lat'], 0.00001);
        $this->assertSame('ABC 1234', $data[0]['plate']);
        $this->assertTrue($data[0]['live']);

        $this->actingAs($this->field)->get('/tracking')->assertForbidden();
        $this->actingAs($this->field)->getJson('/tracking/positions')->assertForbidden();
    }

    public function test_every_truck_of_a_split_shipment_shows_on_the_map(): void
    {
        $s = $this->shipment();
        $otherUser = $this->user(Role::FieldPersonnel);
        DB::table('vehicles')->insert(['id' => 2, 'plate_number' => 'XYZ 9876']);
        $leg = Driver::create(['name' => 'Second Driver', 'user_id' => $otherUser->id, 'vehicle_id' => 2]);
        DB::table('shipment_vehicle_allocations')->insert(['shipment_id' => $s->shipment_id, 'vehicle_id' => 2, 'driver_id' => $leg->id, 'sequence' => 2]);

        $this->ping($s, ['latitude' => 10.0]);
        $this->travel(30)->seconds();
        $this->ping($s, ['latitude' => 11.0], $otherUser);
        $this->travel(30)->seconds();
        $this->ping($s, ['latitude' => 10.2]); // first truck moves on

        $data = collect($this->actingAs($this->user(Role::Manager))->getJson('/tracking/positions')->json());
        $this->assertCount(2, $data, 'one marker per truck, not one per shipment');
        $this->assertEqualsWithDelta(10.2, $data->firstWhere('plate', 'ABC 1234')['lat'], 0.00001, "each truck's latest position");
        $this->assertEqualsWithDelta(11.0, $data->firstWhere('plate', 'XYZ 9876')['lat'], 0.00001);
    }

    public function test_positions_older_than_90_days_are_deleted(): void
    {
        $s = $this->shipment();
        VehicleLocationPing::create(['driver_id' => $this->driver->id, 'shipment_id' => $s->shipment_id, 'latitude' => 1, 'longitude' => 1, 'recorded_at' => now()->subDays(91)]);
        VehicleLocationPing::create(['driver_id' => $this->driver->id, 'shipment_id' => $s->shipment_id, 'latitude' => 2, 'longitude' => 2, 'recorded_at' => now()->subDays(89)]);

        Artisan::call('model:prune', ['--model' => [VehicleLocationPing::class]]);

        $this->assertSame([2.0], VehicleLocationPing::pluck('latitude')->all());
    }
}
