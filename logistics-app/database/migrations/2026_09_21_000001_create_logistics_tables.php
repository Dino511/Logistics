<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number')->unique();
            $t->string('type');
            $t->decimal('capacity_kg', 10, 2)->nullable();
            $t->string('status')->default('available'); // available, on_road, maintenance
            $t->timestamps();
        });

        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('license_number')->unique();
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('address')->nullable();
            $t->timestamps();
        });

        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique(); // e.g. SH-10482
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $t->string('origin');
            $t->string('destination');
            $t->string('status')->default('pending'); // pending, in_transit, delivered, delayed
            $t->decimal('weight_kg', 10, 2)->nullable();
            $t->dateTime('eta')->nullable();
            $t->dateTime('delivered_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
    }
};
