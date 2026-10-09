<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Shipment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class ShipmentPrintTest extends TestCase
{
    use MigratesCoreTables;

    protected function setUp(): void
    {
        parent::setUp();

        // The real tables come from SQL Server-only migrations; create the columns used here.
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('tracking_number');
            $t->string('status');
            $t->string('origin_name')->nullable();
            $t->string('origin_address')->nullable();
            $t->string('origin_city')->nullable();
            $t->string('origin_province')->nullable();
            $t->string('origin_postal_code')->nullable();
            $t->string('destination_name')->nullable();
            $t->string('destination_address')->nullable();
            $t->string('destination_city')->nullable();
            $t->string('destination_province')->nullable();
            $t->string('destination_postal_code')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->dateTime('scheduled_pickup_at')->nullable();
            $t->dateTime('dispatched_at')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->dateTime('actual_delivery_at')->nullable();
            $t->string('delivery_result')->nullable();
            $t->string('received_by')->nullable();
            $t->string('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id('shipment_item_id');
            $t->unsignedBigInteger('shipment_id');
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
            'scheduled_delivery_at' => now()->addDay(),
        ]);
    }

    public function test_a_shipment_prints_with_its_items_totals_and_history(): void
    {
        $s = $this->shipment('SH-PRINT-1', ['notes' => 'Fragile, keep upright']);
        DB::table('shipment_items')->insert([
            ['shipment_id' => $s->shipment_id, 'sku' => 'BX-01', 'item_name' => 'Boxes', 'quantity' => 100, 'unit_weight_kg' => 1.5],
            ['shipment_id' => $s->shipment_id, 'sku' => 'TP-02', 'item_name' => 'Tape', 'quantity' => 50, 'unit_weight_kg' => 0.2],
        ]);
        DB::table('shipment_status_history')->insert(['shipment_id' => $s->shipment_id, 'status' => 'pending', 'note' => 'Shipment created.', 'changed_at' => now()]);
        $user = $this->user(Role::LogisticsCoordinator);

        $this->actingAs($user)->get("/shipments/{$s->shipment_id}/print")
            ->assertOk()
            ->assertSee('<title>Shipment SH-PRINT-1</title>', false)
            ->assertSee('Main warehouse')->assertSee('Batangas City')
            ->assertSee('BX-01')->assertSee('Tape')
            ->assertSee('160.00 kg')
            ->assertSee('Fragile, keep upright')
            ->assertSee('Shipment created.')
            ->assertSee('window.print()', false);

        $this->assertDatabaseHas('activity_logs', ['action' => 'report', 'user_id' => $user->id, 'description' => 'Printed shipment SH-PRINT-1']);
    }

    public function test_the_list_prints_with_the_pages_filters_applied(): void
    {
        $this->shipment('SH-PEND');
        $this->shipment('SH-DONE', ['status' => 'delivered', 'delivery_result' => 'late', 'destination_city' => 'Lipa']);
        $user = $this->user(Role::Manager);

        $this->actingAs($user)->get('/shipments/print')
            ->assertOk()->assertSee('SH-PEND')->assertSee('SH-DONE')->assertSee('2 shipments')->assertSee('none (all shipments)');

        $this->actingAs($user)->get('/shipments/print?status=delivered')
            ->assertOk()->assertSee('SH-DONE')->assertDontSee('SH-PEND')->assertSee('Status: Delivered')->assertSee('Late');

        $this->actingAs($user)->get('/shipments/print?q=Lipa')
            ->assertOk()->assertSee('SH-DONE')->assertDontSee('SH-PEND');
    }

    public function test_the_shipment_pages_link_to_their_printable_copies(): void
    {
        $s = $this->shipment('SH-LINK');
        $user = $this->user(Role::Manager);

        $this->actingAs($user)->get('/shipments?status=pending')->assertOk()->assertSee('/shipments/print?status=pending', false);
        $this->actingAs($user)->get("/shipments/{$s->shipment_id}/print")->assertOk()->assertSee("/shipments/{$s->shipment_id}", false);
    }

    public function test_guests_cannot_print(): void
    {
        $s = $this->shipment('SH-GUEST');

        $this->get('/shipments/print')->assertRedirect('/login');
        $this->get("/shipments/{$s->shipment_id}/print")->assertRedirect('/login');
    }
}
