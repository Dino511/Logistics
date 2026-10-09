<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Texts a Super Admin edits in the app, shown on the Field Personnel dashboard.
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique(); // see SiteContent::SECTIONS
            $table->string('title', 100);
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Starter text, meant to be replaced with the company's own.
        $now = now();
        DB::table('site_contents')->insert([
            [
                'key' => 'guide',
                'title' => 'How to use this app',
                'body' => "- Open a delivery from \"My deliveries\" to see the route, items and notes.\n"
                    ."- Press \"Start sharing\" when you leave, so the office can see where the delivery is. Keep the page open.\n"
                    ."- When you arrive, choose \"Delivered\", enter who received it and take a photo.\n"
                    ."- If you are held up, choose \"Delayed\" and give the reason.\n"
                    ."- Use Notes to message the office about this delivery.\n"
                    .'- In an emergency, press the red Emergency button: call 911 first, then send an SOS.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'guidelines',
                'title' => 'Safety & delivery guidelines',
                'body' => "- Check the load and the delivery details before you leave.\n"
                    ."- Do not use your phone while driving. Pull over safely first.\n"
                    ."- Follow traffic rules and speed limits at all times.\n"
                    ."- Hand over goods only to the named recipient or an authorised person, and take a clear photo as proof.\n"
                    ."- Report delays, damage or problems through the app as soon as it is safe.\n"
                    .'- Keep the truck locked and the cargo secure when parked.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'company',
                'title' => 'About the company',
                'body' => "Replace this text with your company profile: who you are, what you deliver, and your mission.\n\n"
                    .'Add the main office address and contact number here too.',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
