<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Shipment;
use App\Models\User;
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
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->string('status');
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

        // The message sits with the schedule fields, not in the list at the top of the form.
        $this->followingRedirects()->from('/shipments/create')->actingAs($this->coordinator)->post('/shipments', ['scheduled_pickup_at' => ''])
            ->assertSee('id="schedule-errors"', false)->assertSee('Enter the scheduled pickup date and time.');
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
}
