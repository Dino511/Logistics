<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Positions sent from a driver's phone while they share their location during a
        // delivery. Personal data under RA 10173: deleted after 90 days (VehicleLocationPing::prunable).
        Schema::create('vehicle_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedBigInteger('shipment_id');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['shipment_id', 'recorded_at']);
            $table->index(['driver_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_location_pings');
    }
};
