<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which pickup stop an item is collected at, for shipments with several stops
 * (shipment_pickups.sequence). Null on single-pickup shipments: everything is at the origin.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The table only exists where the SQL Server schema was installed.
        if (Schema::hasTable('shipment_items') && ! Schema::hasColumn('shipment_items', 'pickup_sequence')) {
            Schema::table('shipment_items', fn (Blueprint $table) => $table->unsignedSmallInteger('pickup_sequence')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipment_items') && Schema::hasColumn('shipment_items', 'pickup_sequence')) {
            Schema::table('shipment_items', fn (Blueprint $table) => $table->dropColumn('pickup_sequence'));
        }
    }
};
