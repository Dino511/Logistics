<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numbers drivers can call with one tap from the Emergency button.
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 30);
            $table->string('category', 20); // see EmergencyContact::CATEGORIES
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The national emergency hotline is always a sensible first entry.
        DB::table('emergency_contacts')->insert([
            'name' => 'National Emergency Hotline',
            'phone' => '911',
            'category' => 'emergency',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};
