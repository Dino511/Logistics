<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which Field Personnel account (if any) a driver signs in as, so that person can
        // update the deliveries assigned to them.
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // One account per driver. SQL Server treats NULLs as equal in a plain unique
        // index, so it needs a filtered index; other databases already allow many NULLs.
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX drivers_user_id_unique ON drivers (user_id) WHERE user_id IS NOT NULL');
        } else {
            Schema::table('drivers', fn (Blueprint $table) => $table->unique('user_id'));
        }

        // Proof of delivery, filled in when the shipment is marked Delivered from the field.
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('proof_photo_path')->nullable();
            $table->string('received_by', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['proof_photo_path', 'received_by']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique('drivers_user_id_unique');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
