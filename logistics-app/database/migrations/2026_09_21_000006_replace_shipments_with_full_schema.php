<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replace the earlier placeholder tables, but never throw away real data.
        foreach (['shipment_items', 'shipments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id') && DB::table($table)->exists()) {
                throw new RuntimeException("Table [{$table}] has rows; move the data before running this migration.");
            }
        }
        // Only drop the old-layout tables (they have an `id` column; the new ones use shipment_id).
        if (Schema::hasTable('shipment_items') && Schema::hasColumn('shipment_items', 'id')) {
            Schema::drop('shipment_items');
        }
        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'id')) {
            Schema::drop('shipments');
        }

        // The script is idempotent and split into batches at each GO line.
        $sql = file_get_contents(database_path('sql/shipments_schema.sql'));
        foreach (preg_split('/^\s*GO\s*$/mi', $sql) as $batch) {
            if (trim($batch) !== '') {
                DB::unprepared($batch);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_history');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
