<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            // Refer to rows in the Inventory database. No FK: constraints can't span databases.
            $t->unsignedBigInteger('product_id')->index();
            $t->unsignedBigInteger('from_location_id')->nullable()->index();
            $t->unsignedInteger('quantity');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
