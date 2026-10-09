<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Truck / cargo helpers: the crew who ride along and handle the load. Kept apart from
 * drivers because a helper has no licence and is never the one a shipment is assigned to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            // The truck this helper usually rides with.
            $table->unsignedBigInteger('vehicle_id')->nullable();
            // The Field Personnel account (position Helper) this person signs in with, if any.
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // The vehicles table only exists where the SQL Server schema was installed.
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE helpers ADD CONSTRAINT FK_helpers_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE helpers ADD CONSTRAINT FK_helpers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpers');
    }
};
