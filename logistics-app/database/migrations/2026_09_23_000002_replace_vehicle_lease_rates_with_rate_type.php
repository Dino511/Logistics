<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_leases', function (Blueprint $table) {
            $table->dropColumn(['rate_daily', 'rate_monthly', 'rate_annual']);

            // A single rate replaces the three separate columns: one frequency + one amount.
            // SQL Server has no native ENUM, so rate_type is a plain string constrained by a
            // CHECK constraint (same pattern the app's other status-style columns could use;
            // Laravel's own validation is the primary guard, this is a second line of defence).
            $table->string('rate_type', 20)->nullable()->after('lessor_name');
            $table->decimal('rate_amount', 12, 2)->nullable()->after('rate_type');
        });

        DB::statement("
            ALTER TABLE vehicle_leases
            ADD CONSTRAINT CK_vehicle_leases_rate_type CHECK (rate_type IN ('daily', 'monthly', 'annual'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicle_leases DROP CONSTRAINT CK_vehicle_leases_rate_type');

        Schema::table('vehicle_leases', function (Blueprint $table) {
            $table->dropColumn(['rate_type', 'rate_amount']);
            $table->decimal('rate_daily', 10, 2)->nullable();
            $table->decimal('rate_monthly', 10, 2)->nullable();
            $table->decimal('rate_annual', 12, 2)->nullable();
        });
    }
};
