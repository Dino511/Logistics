<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a Field Personnel account does on the truck: driver or helper (User::FIELD_POSITIONS).
        // Empty for every other role.
        Schema::table('users', function (Blueprint $table) {
            $table->string('field_position', 20)->nullable();
        });

        // Accounts already linked to a driver record are drivers.
        if (Schema::hasColumn('drivers', 'user_id')) {
            DB::table('users')
                ->where('role', 'field_personnel')
                ->whereIn('id', DB::table('drivers')->whereNotNull('user_id')->select('user_id'))
                ->update(['field_position' => 'driver']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('field_position');
        });
    }
};
