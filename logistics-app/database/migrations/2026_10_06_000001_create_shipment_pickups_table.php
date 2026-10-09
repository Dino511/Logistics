<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pickup stops for shipments that collect from more than one place. A shipment with a
 * single pickup has no rows here: its origin_* columns are the whole story. With several,
 * every stop is listed in visiting order, and stop 1 repeats the shipment's origin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 150);
            $table->string('address', 255);
            $table->string('city', 100);
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            // Set when the driver (or the office) confirms the goods were collected here.
            $table->dateTime('picked_up_at')->nullable();
            $table->unsignedBigInteger('picked_up_by')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'sequence']);
        });

        // The shipments table only exists on SQL Server (see database/sql/shipments_schema.sql).
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE shipment_pickups ADD CONSTRAINT FK_shipment_pickups_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (shipment_id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_pickups');
    }
};
