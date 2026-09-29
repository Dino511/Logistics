<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per vehicle used to fulfil a shipment. A shipment normally needs
        // just one row here; a heavy order that had to be split across trucks gets
        // one row per truck, all sharing the same shipment_id.
        Schema::create('shipment_vehicle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments', 'shipment_id')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // Which leg of the split this is: 1st truck, 2nd truck, ...
            $table->unsignedSmallInteger('sequence');
            $table->decimal('allocated_weight_kg', 10, 2);
            $table->decimal('vehicle_capacity_kg', 10, 2); // snapshot: the vehicle's capacity when allocated
            $table->string('status', 20)->default('pending'); // pending, dispatched, delivered, cancelled

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'vehicle_id']);
            $table->index(['vehicle_id', 'status']);
        });

        // Which shipment item (and how much of it) rode on which allocation/truck.
        // A single item's quantity can span more than one truck when it alone is
        // heavier than any single vehicle's capacity.
        Schema::create('shipment_allocation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained('shipment_vehicle_allocations')->cascadeOnDelete();
            // NO ACTION here: shipments already cascades to shipment_items, and a second
            // cascade path through this table (via allocation_id) is what SQL Server rejects.
            // Deleting an allocation (or its shipment) already removes these rows via allocation_id.
            $table->foreignId('shipment_item_id')->constrained('shipment_items', 'shipment_item_id');
            $table->unsignedInteger('quantity');
            $table->decimal('weight_kg', 10, 2);
            $table->timestamps();

            $table->index('shipment_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_allocation_items');
        Schema::dropIfExists('shipment_vehicle_allocations');
    }
};
