<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Helper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class HelperTest extends TestCase
{
    use MigratesCoreTables;

    protected function setUp(): void
    {
        parent::setUp();

        // The real tables come from SQL Server-only migrations; create the columns used here.
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->string('type')->nullable();
            $t->timestamps();
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('license_number')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('status');
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->timestamps();
        });
    }

    public function test_a_helper_can_be_added_and_is_listed_on_the_drivers_page(): void
    {
        $coordinator = $this->user(Role::LogisticsCoordinator);
        $truck = DB::table('vehicles')->insertGetId(['plate_number' => 'ABC 1234', 'type' => 'Truck']);

        $this->actingAs($coordinator)->get('/helpers/create')->assertOk()->assertSee('Add helper');

        $this->actingAs($coordinator)->post('/helpers', [
            'name' => 'Hector Helper', 'phone' => '+639171234567', 'vehicle_id' => $truck, 'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect('/drivers');

        $this->assertDatabaseHas('helpers', ['name' => 'Hector Helper', 'vehicle_id' => $truck, 'status' => 'active']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'description' => 'Added helper Hector Helper']);

        $this->actingAs($coordinator)->get('/drivers')->assertOk()
            ->assertSee('Truck / cargo helpers')->assertSee('Hector Helper')->assertSee('ABC 1234')->assertSee('1 helper');
    }

    public function test_a_helper_needs_a_name_a_valid_phone_and_a_helper_account(): void
    {
        $coordinator = $this->user(Role::LogisticsCoordinator);
        $driverAccount = $this->user(Role::FieldPersonnel);
        $driverAccount->forceFill(['field_position' => 'driver'])->save();
        $helperAccount = $this->user(Role::FieldPersonnel);
        $helperAccount->forceFill(['field_position' => 'helper'])->save();

        $this->actingAs($coordinator)->post('/helpers', ['name' => '', 'phone' => '0917 123', 'status' => 'retired'])
            ->assertSessionHasErrors(['name', 'phone', 'status']);
        // Only an account whose position is Helper can be linked, and only to one helper.
        $this->actingAs($coordinator)->post('/helpers', ['name' => 'A', 'status' => 'active', 'user_id' => $driverAccount->id])->assertSessionHasErrors('user_id');
        $this->actingAs($coordinator)->post('/helpers', ['name' => 'A', 'status' => 'active', 'user_id' => $helperAccount->id])->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->post('/helpers', ['name' => 'B', 'status' => 'active', 'user_id' => $helperAccount->id])->assertSessionHasErrors('user_id');

        $this->assertSame(1, Helper::count());
    }

    public function test_a_helper_can_be_edited_and_only_a_manager_can_delete_one(): void
    {
        $helper = Helper::create(['name' => 'Hector Helper', 'status' => 'active']);
        $coordinator = $this->user(Role::LogisticsCoordinator);

        $this->actingAs($coordinator)->get("/helpers/{$helper->id}/edit")->assertOk()->assertSee('Hector Helper');
        $this->actingAs($coordinator)->put("/helpers/{$helper->id}", ['name' => 'Hector H.', 'status' => 'on_leave'])
            ->assertSessionHasNoErrors()->assertRedirect('/drivers');
        $this->assertDatabaseHas('helpers', ['id' => $helper->id, 'name' => 'Hector H.', 'status' => 'on_leave']);

        $this->actingAs($coordinator)->delete("/helpers/{$helper->id}")->assertForbidden();
        $this->actingAs($this->user(Role::Manager))->delete("/helpers/{$helper->id}")->assertRedirect();
        $this->assertDatabaseMissing('helpers', ['id' => $helper->id]);
    }

    public function test_field_personnel_cannot_manage_helpers(): void
    {
        $this->actingAs($this->user(Role::FieldPersonnel))->get('/helpers/create')->assertForbidden();
        $this->actingAs($this->user(Role::FieldPersonnel))->post('/helpers', ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
