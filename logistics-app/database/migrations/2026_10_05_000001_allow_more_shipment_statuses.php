<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The shipments and shipment_status_history tables only accept a fixed list of statuses
 * (CHECK constraints from database/sql/shipments_schema.sql). Widen both lists to the
 * statuses in App\Models\Shipment::STATUSES.
 */
return new class extends Migration
{
    private const TABLES = ['shipments' => 'CK_shipments_status', 'shipment_status_history' => 'CK_ssh_status'];

    private const OLD = ['pending', 'in_transit', 'delivered', 'delayed', 'cancelled'];

    private const NEW = [
        'pending', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery',
        'delivery_attempted', 'held_for_pickup', 'delayed', 'delivered', 'returned', 'cancelled',
    ];

    public function up(): void
    {
        $this->replace(self::NEW);
    }

    public function down(): void
    {
        // Fails if any row already uses one of the newer statuses; change those rows first.
        $this->replace(self::OLD);
    }

    private function replace(array $statuses): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        $list = implode(', ', array_map(fn ($s) => "N'{$s}'", $statuses));
        foreach (self::TABLES as $table => $constraint) {
            DB::statement("IF OBJECT_ID(N'{$constraint}', N'C') IS NOT NULL ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (status IN ({$list}))");
        }
    }
};
