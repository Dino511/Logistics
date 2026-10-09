<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The truck / cargo helper riding along on a shipment, alongside its driver and vehicle. */
return new class extends Migration
{
    public function up(): void
    {
        // The table only exists where the SQL Server schema was installed.
        if (! Schema::hasTable('shipments') || Schema::hasColumn('shipments', 'helper_id')) {
            return;
        }

        Schema::table('shipments', fn (Blueprint $table) => $table->unsignedBigInteger('helper_id')->nullable());

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE shipments ADD CONSTRAINT FK_shipments_helper FOREIGN KEY (helper_id) REFERENCES helpers (id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments') || ! Schema::hasColumn('shipments', 'helper_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT FK_shipments_helper');
        }
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn('helper_id'));
    }
};
