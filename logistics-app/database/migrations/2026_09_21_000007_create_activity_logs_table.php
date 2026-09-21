<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Who did it. user_id may become NULL if the account is deleted, so the name and
            // role are copied here and the log keeps reading correctly.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255);
            $table->string('user_role', 50)->nullable();
            $table->string('action', 40)->index();
            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 1000);
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
