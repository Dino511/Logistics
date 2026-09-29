<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profile photos become site_images rows owned by a user (name "user_avatar_{id}").
        Schema::table('site_images', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $now = now();
        foreach (DB::table('users')->whereNotNull('avatar_path')->get(['id', 'avatar_path']) as $user) {
            DB::table('site_images')->insert([
                'user_id' => $user->id,
                'name' => "user_avatar_{$user->id}",
                'path' => $user->avatar_path,
                'disk' => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
        });

        foreach (DB::table('site_images')->whereNotNull('user_id')->get(['user_id', 'path']) as $image) {
            DB::table('users')->where('id', $image->user_id)->update(['avatar_path' => $image->path]);
        }

        DB::table('site_images')->whereNotNull('user_id')->delete();

        Schema::table('site_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
