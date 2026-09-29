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

    private function createShipment(int $quantity)
    {
        return $this->actingAs($this->coordinator)->post('/shipments', [
            'origin_name' => 'Main Store', 'origin_address' => '1 Rizal St', 'origin_city' => 'Manila',
            'destination_name' => 'Branch', 'destination_address' => '2 Mabini St', 'destination_city' => 'Quezon City',
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
}
