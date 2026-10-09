<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A conversation thread on each shipment, between office staff and its drivers.
        Schema::create('shipment_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->index();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        // Per-user alerts shown under the bell: new notes, delays, deliveries, assignments.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 40);
            $table->string('message', 255);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('shipment_notes');
    }
};
