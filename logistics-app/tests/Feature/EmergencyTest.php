<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Driver;
use App\Models\EmergencyContact;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VehicleLocationPing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class EmergencyTest extends TestCase
{
    use MigratesCoreTables;

    private User $field;

    private User $manager;

    private User $admin;

    private User $coordinator;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // The real fleet and shipment tables come from SQL Server-only migrations.
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('plate_number');
            $t->string('status')->default('available');
        });
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id('shipment_id');
            $t->string('tracking_number');
            $t->string('status');
            $t->string('destination_name')->nullable();
            $t->string('destination_city')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->text('notes')->nullable();
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->integer('sequence')->default(1);
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id('shipment_item_id');
            $t->unsignedBigInteger('shipment_id');
            $t->integer('quantity');
        });
        (require base_path('database/migrations/2026_09_24_000002_create_vehicle_location_pings_table.php'))->up();

        DB::table('vehicles')->insert(['id' => 1, 'plate_number' => 'ASD111']);
        $this->field = $this->user(Role::FieldPersonnel);
        $this->manager = $this->user(Role::Manager);
        $this->admin = $this->user(Role::SuperAdmin);
        $this->coordinator = $this->user(Role::LogisticsCoordinator);
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id, 'phone' => '+639171234567', 'vehicle_id' => 1]);
    }

    private function shipment(string $status = 'in_transit'): Shipment
    {
        return Shipment::forceCreate([
            'tracking_number' => 'SH-SOS-'.random_int(1000, 9999), 'status' => $status,
            'destination_name' => 'Branch', 'destination_city' => 'Pasig',
            'driver_id' => $this->driver->id, 'vehicle_id' => 1, 'created_by' => $this->coordinator->id,
        ]);
    }

    public function test_911_is_there_from_the_start(): void
    {
        $this->assertDatabaseHas('emergency_contacts', ['phone' => '911', 'category' => 'emergency']);
    }

    public function test_managers_can_add_edit_and_remove_contacts(): void
    {
        $this->actingAs($this->manager)->get('/emergency-contacts')->assertOk()->assertSee('National Emergency Hotline');

        $this->post('/emergency-contacts', ['name' => 'Dispatch office', 'phone' => '(02) 8123-4567', 'category' => 'company'])
            ->assertSessionHasNoErrors();
        $contact = EmergencyContact::where('name', 'Dispatch office')->firstOrFail();
        $this->assertSame('tel:0281234567', $contact->telHref());

        $this->put("/emergency-contacts/{$contact->id}", ['name' => 'Dispatch', 'phone' => '+63 917 000 1111', 'category' => 'company'])
            ->assertSessionHasNoErrors();
        $this->assertSame('tel:+639170001111', $contact->fresh()->telHref());

        $this->post('/emergency-contacts', ['name' => 'Bad', 'phone' => 'call me', 'category' => 'company'])->assertSessionHasErrors('phone');
        $this->post('/emergency-contacts', ['name' => 'Bad', 'phone' => '123', 'category' => 'aliens'])->assertSessionHasErrors('category');

        $this->delete("/emergency-contacts/{$contact->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($contact);
    }

    public function test_only_valid_philippine_numbers_are_accepted(): void
    {
        $this->actingAs($this->manager);
        $valid = ['09171234567', '0917 123 4567', '+63 917 123 4567', '+639171234567', '(02) 8123-4567', '(032) 123-4567', '+63 2 8123 4567', '911', '143', '8888', '1800-10-123-4567'];
        $invalid = ['0000000000000000000000', '0917123456', '091712345678', '+6391712345', '+63917123456789', '12', '123456', '9171234567', '+1 415 555 0100', '0917-ABC-4567', '012', '0000000000'];

        foreach ($valid as $phone) {
            $this->post('/emergency-contacts', ['name' => "OK {$phone}", 'phone' => $phone, 'category' => 'other'])
                ->assertSessionHasNoErrors();
        }
        foreach ($invalid as $phone) {
            $this->post('/emergency-contacts', ['name' => "Bad {$phone}", 'phone' => $phone, 'category' => 'other'])
                ->assertSessionHasErrors('phone', "{$phone} should be rejected");
        }

        $this->assertSame(count($valid), EmergencyContact::where('name', 'like', 'OK %')->count());
        $this->assertSame(0, EmergencyContact::where('name', 'like', 'Bad %')->count());
    }

    public function test_only_administrators_manage_contacts(): void
    {
        foreach ([$this->admin, $this->manager, $this->coordinator] as $user) {
            $this->actingAs($user)->get('/emergency-contacts')->assertOk();
        }

        $this->actingAs($this->field)->get('/emergency-contacts')->assertForbidden();
        $this->actingAs($this->field)->post('/emergency-contacts', ['name' => 'X', 'phone' => '123', 'category' => 'other'])->assertForbidden();
    }

    public function test_drivers_see_the_emergency_button_with_tap_to_call_numbers(): void
    {
        EmergencyContact::create(['name' => 'Towing', 'phone' => '0917-555-0000', 'category' => 'roadside']);

        $this->actingAs($this->field)->get('/shipments')->assertOk()
            ->assertSee('id="sosOpen"', false)
            ->assertSee('href="tel:911"', false)
            ->assertSee('href="tel:09175550000"', false)
            ->assertSee('call 911 first', false);

        // Office staff don't get the driver's button.
        $this->actingAs($this->manager)->get('/shipments')->assertOk()->assertDontSee('id="sosOpen"', false);
    }

    public function test_sos_alerts_the_managers_and_coordinators_with_the_delivery_and_location(): void
    {
        $s = $this->shipment();
        VehicleLocationPing::create(['driver_id' => $this->driver->id, 'shipment_id' => $s->shipment_id, 'vehicle_id' => 1, 'latitude' => 14.58, 'longitude' => 121.06, 'recorded_at' => now()->subMinutes(2)]);

        $this->actingAs($this->field)->post('/sos', ['shipment_id' => $s->shipment_id, 'message' => 'Flat tyre, unsafe area'])
            ->assertRedirect()->assertSessionHas('warning');

        foreach ([$this->manager, $this->coordinator] as $user) {
            $alert = Alert::where('user_id', $user->id)->where('type', 'sos')->first();
            $this->assertNotNull($alert, "{$user->role->value} is alerted");
            $this->assertStringContainsString('SOS from '.$this->field->name.' (ASD111) on '.$s->tracking_number.': Flat tyre, unsafe area', $alert->message);
            $this->assertSame($s->shipment_id, $alert->shipment_id);
        }
        $this->assertSame(0, Alert::where('user_id', $this->field->id)->count(), 'not the sender');
        $this->assertSame(0, Alert::where('user_id', $this->admin->id)->count(), 'not the Super Admin, who has no part in deliveries');

        $this->assertStringContainsString('SOS sent: Flat tyre, unsafe area. Last location 14.58,121.06', $s->shipmentNotes()->value('body'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'sos', 'user_id' => $this->field->id]);
    }

    public function test_sos_still_works_without_a_delivery_or_location(): void
    {
        $this->actingAs($this->field)->post('/sos')->assertRedirect();

        $alert = Alert::where('user_id', $this->manager->id)->firstOrFail();
        $this->assertNull($alert->shipment_id);
        $this->assertStringContainsString('No recent location', $alert->message);
    }

    public function test_only_field_personnel_send_sos_and_it_is_rate_limited(): void
    {
        $this->actingAs($this->manager)->post('/sos')->assertForbidden();

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->field)->post('/sos')->assertRedirect();
        }
        $this->actingAs($this->field)->post('/sos')->assertStatus(429);
    }

    public function test_office_can_tap_to_call_the_driver_from_live_tracking(): void
    {
        $s = $this->shipment();
        VehicleLocationPing::create(['driver_id' => $this->driver->id, 'shipment_id' => $s->shipment_id, 'vehicle_id' => 1, 'latitude' => 14.58, 'longitude' => 121.06, 'recorded_at' => now()]);

        $data = $this->actingAs($this->coordinator)->getJson('/tracking/positions')->json();
        $this->assertSame('tel:+639171234567', $data[0]['tel']);
    }
}
