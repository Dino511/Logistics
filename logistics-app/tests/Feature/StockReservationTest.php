<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Helper;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CrewAvailability;
use App\Services\StockReservations;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use MigratesCoreTables;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // no real map lookups

        // The real shipment tables are built by a SQL Server-only migration, so create just
        // the columns these tests touch.
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('tracking_number')->unique();
            $t->string('status');
            foreach (['origin', 'destination'] as $end) {
                $t->string("{$end}_name")->nullable();
                $t->string("{$end}_address")->nullable();
                $t->string("{$end}_city")->nullable();
                $t->string("{$end}_province")->nullable();
                $t->string("{$end}_postal_code")->nullable();
                $t->decimal("{$end}_latitude", 10, 7)->nullable();
                $t->decimal("{$end}_longitude", 10, 7)->nullable();
            }
            $t->dateTime('scheduled_pickup_at')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('helper_id')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id('shipment_item_id');
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('inventory_product_id');
            $t->unsignedBigInteger('inventory_location_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('item_name')->nullable();
            $t->integer('quantity');
            $t->decimal('unit_weight_kg', 10, 2)->nullable();
            $t->unsignedSmallInteger('pickup_sequence')->nullable();
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->integer('sequence')->default(1);
            $t->string('status')->default('dispatched');
            $t->dateTime('delivered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_status_history', function (Blueprint $t) {
            $t->id('history_id');
            $t->unsignedBigInteger('shipment_id');
            $t->string('status');
            $t->string('note')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->dateTime('changed_at');
        });

        // The New shipment form lists drivers and vehicles to assign.
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->string('type')->nullable();
            $t->string('status');
            $t->boolean('is_rented')->default(false);
            $t->timestamps();
        });
        Schema::create('vehicle_leases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vehicle_id');
            $t->string('lessor_name')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        // A throwaway Inventory database: one product with 100 units at location 1.
        config(['database.connections.inventory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('inventory');
        Schema::connection('inventory')->create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku');
            $t->boolean('is_active');
        });
        Schema::connection('inventory')->create('inventories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('location_id');
            $t->integer('quantity');
        });
        Schema::connection('inventory')->create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        DB::connection('inventory')->table('locations')->insert(['id' => 1, 'name' => 'Main Store']);
        DB::connection('inventory')->table('products')->insert(['id' => 1, 'name' => 'Canned Sardines', 'sku' => 'CS-155', 'is_active' => 1]);
        DB::connection('inventory')->table('inventories')->insert(['product_id' => 1, 'location_id' => 1, 'quantity' => 100]);

        $this->coordinator = $this->user(Role::LogisticsCoordinator);
    }

    private function createShipment(int $quantity, array $overrides = [])
    {
        // A driver and a vehicle are required and can only be on one open shipment, so each
        // shipment gets its own unless the test names them.
        $overrides += [
            'driver_id' => DB::table('drivers')->insertGetId(['name' => 'Driver '.uniqid(), 'status' => 'active']),
            'vehicle_id' => DB::table('vehicles')->insertGetId(['plate_number' => 'T-'.uniqid(), 'status' => 'available']),
        ];

        return $this->actingAs($this->coordinator)->post('/shipments', $overrides + [
            'origin_name' => 'Main Store', 'origin_address' => '1 Rizal St', 'origin_city' => 'Manila',
            'destination_name' => 'Branch', 'destination_address' => '2 Mabini St', 'destination_city' => 'Quezon City',
            'scheduled_pickup_at' => now()->addHours(2)->format('Y-m-d H:i'),
            'scheduled_delivery_at' => now()->addDay()->format('Y-m-d H:i'),
            'items' => [['key' => '1:1', 'quantity' => $quantity]],
        ]);
    }

    private function reservedForSardines(): int
    {
        return app(StockReservations::class)->reserved()['1:1'] ?? 0;
    }

    public function test_a_new_shipment_reserves_its_stock(): void
    {
        $this->createShipment(60)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(60, $this->reservedForSardines());
    }

    public function test_stock_reserved_by_another_shipment_cannot_be_booked_twice(): void
    {
        $this->createShipment(60)->assertSessionHasNoErrors();

        // 100 on hand, 60 reserved: 50 more is too many, and nothing is saved.
        $this->createShipment(50)->assertSessionHasErrors('items');
        $this->assertStringContainsString('only 40 available (100 in stock, 60 reserved for other shipments)', session('errors')->first('items'));
        $this->assertSame(1, Shipment::count());

        $this->createShipment(40)->assertSessionHasNoErrors();
        $this->assertSame(100, $this->reservedForSardines());
    }

    public function test_delivered_and_cancelled_shipments_release_their_stock(): void
    {
        $this->createShipment(60);
        $this->createShipment(40);

        Shipment::query()->orderBy('shipment_id')->first()->forceFill(['status' => 'delivered'])->save();
        $this->assertSame(40, $this->reservedForSardines());

        Shipment::query()->orderByDesc('shipment_id')->first()->forceFill(['status' => 'cancelled'])->save();
        $this->assertSame(0, $this->reservedForSardines());
    }

    public function test_in_transit_and_delayed_shipments_keep_holding_stock(): void
    {
        $this->createShipment(30);
        $this->createShipment(20);

        Shipment::query()->orderBy('shipment_id')->first()->forceFill(['status' => 'in_transit'])->save();
        Shipment::query()->orderByDesc('shipment_id')->first()->forceFill(['status' => 'delayed'])->save();

        $this->assertSame(50, $this->reservedForSardines());
    }

    public function test_a_busy_lock_returns_a_friendly_error_instead_of_saving(): void
    {
        $lock = Cache::lock('stock-reservations', 30);
        $this->assertTrue($lock->get());

        // Shorten the wait so the test doesn't sit for 10 seconds.
        $this->app->instance(StockReservations::class, new class extends StockReservations
        {
            public function exclusively(\Closure $callback): mixed
            {
                return Cache::lock('stock-reservations', 30)->block(1, $callback);
            }
        });

        $this->createShipment(10)->assertSessionHas('error');
        $this->assertSame(0, Shipment::count());

        $lock->release();
    }

    public function test_the_form_offers_only_the_free_quantity(): void
    {
        $this->createShipment(70);

        $view = $this->actingAs($this->coordinator)->get('/shipments/create')->viewData('stockOptions');
        $this->assertSame(30, collect($view)->firstWhere('key', '1:1')['available']);

        $this->createShipment(30);
        $view = $this->actingAs($this->coordinator)->get('/shipments/create')->viewData('stockOptions');
        $this->assertNull(collect($view)->firstWhere('key', '1:1'), 'fully reserved stock is not offered at all');
    }

    public function test_a_shipment_needs_a_pickup_time_and_a_delivery_that_is_not_before_it(): void
    {
        $this->createShipment(1, ['scheduled_pickup_at' => null])->assertSessionHasErrors('scheduled_pickup_at');

        $this->createShipment(1, [
            'scheduled_pickup_at' => now()->addDays(9)->format('Y-m-d H:i'),
            'scheduled_delivery_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors(['scheduled_delivery_at' => "The scheduled delivery can't be earlier than the scheduled pickup."]);

        $this->assertSame(0, $this->reservedForSardines());

        // The message sits beside the pickup time on the origin stop, not in the list at the top of the form.
        $this->followingRedirects()->from('/shipments/create')->actingAs($this->coordinator)->post('/shipments', ['scheduled_pickup_at' => ''])
            ->assertSee('id="pickup-errors"', false)->assertSee('Enter the scheduled pickup date and time.');
    }

    public function test_extra_pickups_are_saved_as_ordered_stops_starting_with_the_origin(): void
    {
        $this->createShipment(1, [
            'pickup2_name' => 'Supplier A', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
            'pickup2_scheduled_at' => now()->addHours(4)->format('Y-m-d H:i'),
            // Stop 3 left blank on purpose; stop 4 filled in: saved as the third stop.
            'pickup4_name' => 'Supplier B', 'pickup4_address' => '9 Shaw Blvd', 'pickup4_city' => 'Pasig',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $stops = Shipment::firstOrFail()->pickups;
        $this->assertSame([1, 2, 3], $stops->pluck('sequence')->all());
        $this->assertSame(['Main Store', 'Supplier A', 'Supplier B'], $stops->pluck('name')->all());
        $this->assertSame('Manila', $stops[0]->city);
        $this->assertNotNull($stops[0]->scheduled_at);
        $this->assertNotNull($stops[1]->scheduled_at);
        $this->assertNull($stops[2]->scheduled_at);
        $this->assertTrue($stops->every(fn ($s) => ! $s->isCollected()));
    }

    public function test_a_single_pickup_shipment_has_no_stop_rows_and_a_half_filled_stop_is_rejected(): void
    {
        $this->createShipment(1)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shipment_pickups', 0);

        $this->createShipment(1, ['pickup2_name' => 'Supplier A'])->assertSessionHasErrors(['pickup2_address', 'pickup2_city']);
        $this->createShipment(1, [
            'pickup2_name' => 'Supplier A', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
            'pickup2_scheduled_at' => now()->addDays(5)->format('Y-m-d H:i'), // after the delivery
        ])->assertSessionHasErrors('pickup2_scheduled_at');
        $this->assertDatabaseCount('shipment_pickups', 0);
    }

    public function test_items_are_saved_against_the_stop_they_are_collected_at(): void
    {
        $this->createShipment(1, [
            // Stops 2 and 3 are left blank, so the form's stop 4 is saved as the second stop.
            'pickup4_name' => 'Supplier B', 'pickup4_address' => '9 Shaw Blvd', 'pickup4_city' => 'Pasig',
            'items' => [
                ['key' => '1:1', 'quantity' => 3, 'pickup' => 1],
                ['key' => '1:1', 'quantity' => 2, 'pickup' => 4],
                ['key' => '1:1', 'quantity' => 4, 'pickup' => 4], // same product, same stop: merged
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $items = Shipment::firstOrFail()->items->sortBy('pickup_sequence')->values();
        $this->assertSame([[1, 3], [2, 6]], $items->map(fn ($i) => [(int) $i->pickup_sequence, (int) $i->quantity])->all());
        $this->assertSame(9, $this->reservedForSardines());
    }

    public function test_stock_is_checked_across_every_stop_together_and_single_pickups_record_no_stop(): void
    {
        // 60 at each of two stops is 120 of the 100 in stock.
        $this->createShipment(1, [
            'pickup2_name' => 'Supplier A', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
            'items' => [['key' => '1:1', 'quantity' => 60, 'pickup' => 1], ['key' => '1:1', 'quantity' => 60, 'pickup' => 2]],
        ])->assertSessionHasErrors('items');
        $this->assertSame(0, $this->reservedForSardines());

        $this->createShipment(5)->assertSessionHasNoErrors();
        $this->assertNull(Shipment::firstOrFail()->items->first()->pickup_sequence);
    }

    public function test_only_office_staff_can_change_the_driver_and_vehicle_of_an_unfinished_shipment(): void
    {
        $this->createShipment(1)->assertSessionHasNoErrors();
        $this->createShipment(1)->assertSessionHasNoErrors();
        [$first, $second] = Shipment::orderBy('shipment_id')->get()->all();
        $truck = DB::table('vehicles')->insertGetId(['plate_number' => 'NEW 5678', 'status' => 'available']);
        $driver = DB::table('drivers')->insertGetId(['name' => 'Nina New', 'status' => 'active']);
        $crew = ['driver_id' => $driver, 'vehicle_id' => $truck];
        $admin = $this->user(Role::Manager);

        foreach ([Role::SuperAdmin, Role::FieldPersonnel] as $role) {
            $this->actingAs($this->user($role))->patch("/shipments/{$first->shipment_id}/crew", $crew)->assertForbidden();
        }
        // The form is offered to Managers and Coordinators alike.
        $this->actingAs($this->coordinator)->get("/shipments/{$first->shipment_id}")->assertOk()->assertSee("/shipments/{$first->shipment_id}/crew", false);

        // Both are still required, and a crew out on another shipment is refused.
        $this->actingAs($admin)->patch("/shipments/{$first->shipment_id}/crew", ['driver_id' => $driver])->assertSessionHasErrors('vehicle_id');
        $this->actingAs($admin)->patch("/shipments/{$first->shipment_id}/crew", ['driver_id' => $second->driver_id, 'vehicle_id' => $truck])
            ->assertSessionHasErrors('driver_id');

        // Keeping its own vehicle doesn't count as busy.
        $this->actingAs($admin)->patch("/shipments/{$first->shipment_id}/crew", ['driver_id' => $driver, 'vehicle_id' => $first->vehicle_id])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Driver and vehicle updated.');
        $this->actingAs($admin)->patch("/shipments/{$first->shipment_id}/crew", $crew)->assertSessionHasNoErrors();
        $this->assertSame([$driver, $truck], [(int) $first->refresh()->driver_id, (int) $first->vehicle_id]);

        // A finished shipment keeps the crew it had.
        $first->update(['status' => 'delivered']);
        $this->actingAs($admin)->patch("/shipments/{$first->shipment_id}/crew", ['driver_id' => $second->driver_id, 'vehicle_id' => $truck])
            ->assertSessionHas('error');
        $this->assertSame($driver, (int) $first->refresh()->driver_id);
    }

    public function test_a_leased_vehicle_whose_contract_has_ended_is_unavailable(): void
    {
        $leased = function (string $plate, array $lease) {
            $id = DB::table('vehicles')->insertGetId(['plate_number' => $plate, 'type' => 'Hino 300 Series', 'status' => 'available', 'is_rented' => true]);
            DB::table('vehicle_leases')->insert($lease + ['vehicle_id' => $id, 'lessor_name' => 'Rentals Inc', 'start_date' => '2026-01-01', 'status' => 'active']);

            return Vehicle::findOrFail($id);
        };
        $ended = $leased('END 1111', ['end_date' => today()->subDay()->toDateString()]);
        $terminated = $leased('TRM 2222', ['end_date' => today()->addMonth()->toDateString(), 'status' => 'terminated']);
        $endsToday = $leased('TDY 3333', ['end_date' => today()->toDateString()]);
        $openEnded = $leased('OPN 4444', ['end_date' => null]);
        $owned = Vehicle::findOrFail(DB::table('vehicles')->insertGetId(['plate_number' => 'OWN 5555', 'status' => 'available']));

        $this->assertSame([true, true, false, false, false], array_map(fn (Vehicle $v) => $v->leaseEnded(), [$ended, $terminated, $endsToday, $openEnded, $owned]));
        $this->assertSame('Lease ended '.today()->subDay()->format('M j, Y'), $ended->leaseEndedNote());
        $this->assertSame('Lease terminated', $terminated->leaseEndedNote());
        $this->assertSame(['OPN 4444', 'OWN 5555', 'TDY 3333'], Vehicle::leaseNotEnded()->orderBy('plate_number')->pluck('plate_number')->all());

        // The Vehicles page warns about them and shows them as Unavailable.
        $this->actingAs($this->coordinator)->get('/vehicles')->assertOk()
            ->assertSee('2 leased vehicles have ended their contracts')
            ->assertSee('END 1111')->assertSee('lease terminated')
            ->assertSee('<span class="badge b-delayed">Unavailable</span>', false);

        // They aren't offered for a new shipment, and can't be forced onto one.
        $offered = $this->actingAs($this->coordinator)->get('/shipments/create')->assertOk()->viewData('vehicles')->pluck('plate_number')->all();
        $this->assertContains('TDY 3333', $offered);
        $this->assertNotContains('END 1111', $offered);
        $this->assertNotContains('TRM 2222', $offered);
        $this->createShipment(1, ['vehicle_id' => $ended->id])->assertSessionHasErrors([
            'vehicle_id' => 'Vehicle END 1111 is unavailable: its lease contract has ended. Renew the contract on the Vehicles page first.',
        ]);
        $this->assertSame(0, Shipment::count());

        // Renewing the contract makes it usable again.
        DB::table('vehicle_leases')->where('vehicle_id', $ended->id)->update(['end_date' => today()->addMonths(6)->toDateString()]);
        $this->assertFalse($ended->fresh()->leaseEnded());
        $this->createShipment(1, ['vehicle_id' => $ended->id])->assertSessionHasNoErrors();
        $this->actingAs($this->coordinator)->get('/vehicles')->assertOk()->assertSee('A leased vehicle has ended its contract')->assertDontSee('END 1111</a>', false);
    }

    public function test_a_vehicle_driver_or_helper_on_an_unfinished_shipment_cannot_be_put_on_another(): void
    {
        $truck = DB::table('vehicles')->insertGetId(['plate_number' => 'ABC 1234', 'status' => 'available']);
        $driver = DB::table('drivers')->insertGetId(['name' => 'Dan Driver', 'status' => 'active']);
        $helper = Helper::create(['name' => 'Hector Helper', 'status' => 'active'])->id;
        $crew = ['vehicle_id' => $truck, 'driver_id' => $driver, 'helper_id' => $helper];

        $this->createShipment(1, $crew)->assertSessionHasNoErrors();
        $first = Shipment::firstOrFail();
        $this->assertSame([$truck, $driver, $helper], [(int) $first->vehicle_id, (int) $first->driver_id, (int) $first->helper_id]);

        // All three are now tied up until the first shipment is finished.
        $this->createShipment(1, $crew)->assertSessionHasErrors([
            'vehicle_id' => "Vehicle ABC 1234 is still on shipment {$first->tracking_number} (Pending). It can be assigned again once that shipment is delivered, returned or cancelled.",
            'driver_id', 'helper_id',
        ]);
        // One busy resource is enough to refuse, and a shipment with a free crew is still fine.
        $this->createShipment(1, ['helper_id' => $helper])->assertSessionHasErrors('helper_id');
        $this->createShipment(1)->assertSessionHasNoErrors();
        $this->assertSame(2, Shipment::count());

        // A driver and a vehicle are both needed; only the helper can be left out.
        $this->createShipment(1, ['driver_id' => '', 'vehicle_id' => ''])->assertSessionHasErrors([
            'driver_id' => 'Choose the driver for this shipment.',
            'vehicle_id' => 'Choose the vehicle for this shipment.',
        ]);
        $this->assertSame(2, Shipment::count());

        // The form shows them, greyed out, with the shipment they are on.
        $this->actingAs($this->coordinator)->get('/shipments/create')->assertOk()
            ->assertSee("Dan Driver (on {$first->tracking_number})")
            ->assertSee("ABC 1234 (on {$first->tracking_number})")
            ->assertSee("Hector Helper (on {$first->tracking_number})");

        // Still busy while it is on the road...
        $first->update(['status' => 'in_transit']);
        $this->createShipment(1, $crew)->assertSessionHasErrors(['vehicle_id', 'driver_id', 'helper_id']);

        // ...and free again once it is delivered.
        $first->update(['status' => 'delivered']);
        $this->createShipment(1, $crew)->assertSessionHasNoErrors();
        $this->assertSame(3, Shipment::count());
    }

    public function test_finishing_a_dispatched_shipment_puts_its_truck_back_to_available(): void
    {
        $truck = DB::table('vehicles')->insertGetId(['plate_number' => 'ABC 1234', 'status' => 'on_road']);
        $this->createShipment(1)->assertSessionHasNoErrors();
        $shipment = Shipment::firstOrFail();
        // The page offers "Cancelled" to a Manager and not to a Coordinator.
        $this->actingAs($this->coordinator)->get("/shipments/{$shipment->shipment_id}")->assertOk()
            ->assertSee('name="status"', false)->assertDontSee('value="cancelled"', false);
        $this->actingAs($this->user(Role::Manager))->get("/shipments/{$shipment->shipment_id}")->assertOk()->assertSee('value="cancelled"', false);
        $shipment->update(['status' => 'in_transit', 'vehicle_id' => $truck]);
        DB::table('shipment_vehicle_allocations')->insert(['shipment_id' => $shipment->shipment_id, 'vehicle_id' => $truck, 'status' => 'dispatched']);

        $busy = app(CrewAvailability::class)->busy();
        $this->assertSame($shipment->shipment_id, $busy['vehicles'][$truck]->shipment_id);

        // Cancelling is a Manager's decision: a Coordinator isn't offered it and can't force it.
        $this->assertContains('delivered', $shipment->nextStatusesFor($this->coordinator));
        $this->assertNotContains('cancelled', $shipment->nextStatusesFor($this->coordinator));
        $this->actingAs($this->coordinator)->patch("/shipments/{$shipment->shipment_id}/status", ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame('in_transit', $shipment->fresh()->status);

        $manager = $this->user(Role::Manager);
        $this->assertContains('cancelled', $shipment->nextStatusesFor($manager));
        $this->actingAs($manager)->patch("/shipments/{$shipment->shipment_id}/status", ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame('available', DB::table('vehicles')->where('id', $truck)->value('status'));
        $this->assertSame('cancelled', DB::table('shipment_vehicle_allocations')->where('shipment_id', $shipment->shipment_id)->value('status'));
        $this->assertSame([], app(CrewAvailability::class)->busy()['vehicles']);
    }

    public function test_the_same_place_cannot_be_a_pickup_stop_twice(): void
    {
        // Stop 2 repeats the origin (whatever the capitals or spaces), and stop 3 repeats stop 2.
        $this->createShipment(1, [
            'pickup2_name' => ' main store ', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
        ])->assertSessionHasErrors(['pickup2_name' => 'main store is already Stop 1. Add its items there instead of listing it twice.']);

        $this->createShipment(1, [
            'pickup2_name' => 'Supplier A', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
            'pickup3_name' => 'SUPPLIER A', 'pickup3_address' => '9 Shaw Blvd', 'pickup3_city' => 'Pasig',
        ])->assertSessionHasErrors(['pickup3_name' => 'SUPPLIER A is already Stop 2. Add its items there instead of listing it twice.'])
            ->assertSessionDoesntHaveErrors('pickup2_name');
        $this->assertSame(0, Shipment::count());

        // Different places are fine, and the destination may share a name with a stop.
        $this->createShipment(1, [
            'pickup2_name' => 'Supplier A', 'pickup2_address' => '5 Aurora Blvd', 'pickup2_city' => 'Quezon City',
            'destination_name' => 'Supplier A',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Shipment::count());
    }
}
