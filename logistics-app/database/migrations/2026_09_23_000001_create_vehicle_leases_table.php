<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether a vehicle is currently a rented/third-party unit vs. company-owned.
        // Kept on `vehicles` (not inferred from the presence of a lease row) so a vehicle's
        // rental status stays correct even across old, expired lease records.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('is_rented')->default(false)->after('capacity_kg');
        });

        Schema::create('vehicle_leases', function (Blueprint $table) {
            $table->id();
            // One lease record per vehicle. A vehicle switching lessors is a new contract on
            // the same row (start/end dates updated), not a history of past leases — add a
            // separate history table later if that history needs to be kept.
            $table->foreignId('vehicle_id')->unique()->constrained('vehicles')->cascadeOnDelete();

            $table->string('lessor_name', 150);

            // Nullable, decimal (never float) since these are money: at least one must be
            // set, enforced in the request validation rather than the database.
            $table->decimal('rate_daily', 10, 2)->nullable();
            $table->decimal('rate_monthly', 10, 2)->nullable();
            $table->decimal('rate_annual', 12, 2)->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable(); // open-ended contracts are allowed
            $table->string('status', 20)->default('active'); // active, expired, terminated

            $table->string('document_path')->nullable(); // signed contract PDF, on the public disk

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_leases');
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('is_rented');
        });
    }
};
