<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class FieldDeliveryTest extends TestCase
{
    use MigratesCoreTables;

    private User $field;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // The real tables come from SQL Server-only migrations; create the columns used here.
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique();
            $t->string('name');
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
            $t->dateTime('scheduled_delivery_at')->nullable();
            $t->dateTime('dispatched_at')->nullable();
            $t->dateTime('actual_delivery_at')->nullable();
            $t->string('proof_photo_path')->nullable();
            $t->string('received_by')->nullable();
            $t->timestamps();
        });
        Schema::create('shipment_status_history', function (Blueprint $t) {
            $t->id('history_id');
            $t->unsignedBigInteger('shipment_id');
            $t->string('status');
            $t->string('note')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->dateTime('changed_at');
        });
        Schema::create('shipment_vehicle_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->integer('sequence')->default(1);
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id('shipment_item_id');
            $t->unsignedBigInteger('shipment_id');
            $t->integer('quantity');
        });

        $this->field = $this->user(Role::FieldPersonnel);
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id, 'status' => 'active']);
    }

    private function shipment(string $status = 'in_transit', ?int $driverId = null): Shipment
    {
        return Shipment::forceCreate([
            'tracking_number' => 'SH-TEST-'.random_int(1000, 9999),
            'status' => $status,
            'destination_name' => 'Branch',
            'destination_city' => 'Quezon City',
            'driver_id' => $driverId ?? $this->driver->id,
            'scheduled_delivery_at' => now()->addHours(3),
            'dispatched_at' => now()->subHour(),
        ]);
    }

    private function update(Shipment $s, array $data)
    {
        return $this->actingAs($this->field)->post("/shipments/{$s->shipment_id}/field-update", $data);
    }

    public function test_marking_delivered_saves_the_photo_receiver_and_history(): void
    {
        $s = $this->shipment();

        $this->update($s, [
            'status' => 'delivered',
            'received_by' => 'Ana Reyes',
            'proof_photo' => UploadedFile::fake()->image('pod.jpg', 800, 600),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $s->refresh();
        $this->assertSame('delivered', $s->status);
        $this->assertSame('Ana Reyes', $s->received_by);
        $this->assertNotNull($s->actual_delivery_at);
        Storage::disk('public')->assertExists($s->proof_photo_path);
        $this->assertDatabaseHas('shipment_status_history', ['shipment_id' => $s->shipment_id, 'status' => 'delivered', 'note' => 'Received by Ana Reyes.', 'changed_by' => $this->field->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'status_changed', 'user_id' => $this->field->id]);
    }

    public function test_the_form_must_have_a_chosen_status_and_says_when_sharing_stops(): void
    {
        $s = $this->shipment();

        // The form starts on "Choose what happened…", which sends no status.
        $this->update($s, ['received_by' => 'Ana', 'proof_photo' => UploadedFile::fake()->image('pod.jpg')])->assertSessionHasErrors('status');
        $this->assertSame('in_transit', $s->fresh()->status);

        $this->update($s, ['status' => 'delivered', 'received_by' => 'Ana', 'proof_photo' => UploadedFile::fake()->image('pod.jpg')])
            ->assertSessionHas('status', 'Delivery updated: Delivered. Location sharing has stopped because the delivery is finished.');
    }

    public function test_delivered_needs_a_photo_and_a_receiver(): void
    {
        $s = $this->shipment();

        $this->update($s, ['status' => 'delivered'])->assertSessionHasErrors(['received_by', 'proof_photo']);
        $this->update($s, ['status' => 'delivered', 'received_by' => 'Ana', 'proof_photo' => UploadedFile::fake()->create('pod.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('proof_photo');

        $this->assertSame('in_transit', $s->fresh()->status);
    }

    public function test_delayed_needs_a_reason(): void
    {
        $s = $this->shipment();

        $this->update($s, ['status' => 'delayed'])->assertSessionHasErrors('note');
        $this->update($s, ['status' => 'delayed', 'note' => 'Road closed at EDSA'])->assertSessionHasNoErrors();

        $this->assertSame('delayed', $s->fresh()->status);

        // And back on the road from Delayed.
        $this->update($s->fresh(), ['status' => 'in_transit'])->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $s->fresh()->status);
    }

    public function test_field_personnel_cannot_cancel_or_start_a_pending_shipment(): void
    {
        $inTransit = $this->shipment();
        $this->update($inTransit, ['status' => 'cancelled'])->assertSessionHasErrors('status');

        $pending = $this->shipment('pending');
        $this->update($pending, ['status' => 'in_transit'])->assertSessionHasErrors('status');

        $this->assertSame('in_transit', $inTransit->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_only_the_assigned_driver_can_update(): void
    {
        $otherDriver = Driver::create(['name' => 'Someone Else', 'status' => 'active']);
        $notMine = $this->shipment('in_transit', $otherDriver->id);

        $this->update($notMine, ['status' => 'delayed', 'note' => 'x'])->assertForbidden();

        // Not linked to any driver at all.
        $unlinked = $this->user(Role::FieldPersonnel);
        $this->actingAs($unlinked)->post("/shipments/{$notMine->shipment_id}/field-update", ['status' => 'delayed', 'note' => 'x'])->assertForbidden();

        // Office roles use the normal status form, not this one.
        $manager = $this->user(Role::Manager);
        $this->actingAs($manager)->post("/shipments/{$this->shipment()->shipment_id}/field-update", ['status' => 'delayed', 'note' => 'x'])->assertForbidden();

        $this->assertSame('in_transit', $notMine->fresh()->status);
    }

    public function test_a_driver_on_one_leg_of_a_split_shipment_counts_as_assigned(): void
    {
        $lead = Driver::create(['name' => 'Lead Driver', 'status' => 'active']);
        $split = $this->shipment('in_transit', $lead->id);
        DB::table('shipment_vehicle_allocations')->insert(['shipment_id' => $split->shipment_id, 'driver_id' => $this->driver->id, 'sequence' => 2]);

        $this->update($split, ['status' => 'delayed', 'note' => 'Flat tyre'])->assertSessionHasNoErrors();
        $this->assertSame('delayed', $split->fresh()->status);
    }

    public function test_my_deliveries_lists_only_my_open_shipments(): void
    {
        $mine = $this->shipment();
        $this->shipment('delivered');
        $otherDriver = Driver::create(['name' => 'Someone Else', 'status' => 'active']);
        $this->shipment('in_transit', $otherDriver->id);

        $list = $this->actingAs($this->field)->get('/shipments')->assertOk()->viewData('myDeliveries');

        $this->assertSame([$mine->shipment_id], $list->pluck('shipment_id')->all());
    }

    public function test_a_driver_can_report_a_failed_attempt_only_with_a_reason(): void
    {
        $s = $this->shipment('out_for_delivery');

        $this->update($s, ['status' => 'delivery_attempted'])->assertSessionHasErrors('note');
        $this->update($s, ['status' => 'delivery_attempted', 'note' => 'Gate locked, nobody answered'])->assertSessionHasNoErrors();

        $this->assertSame('delivery_attempted', $s->refresh()->status);
        $this->assertDatabaseHas('shipment_status_history', ['shipment_id' => $s->shipment_id, 'status' => 'delivery_attempted', 'note' => 'Gate locked, nobody answered']);
    }

    public function test_a_driver_moves_a_delivery_through_the_new_steps_but_cannot_return_or_cancel_it(): void
    {
        $s = $this->shipment('ready_for_pickup');
        $this->assertSame(['picked_up'], $s->fieldNextStatuses());

        $this->update($s, ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->update($s->refresh(), ['status' => 'out_for_delivery'])->assertSessionHasNoErrors();
        $this->update($s->refresh(), ['status' => 'delivery_attempted', 'note' => 'No one home'])->assertSessionHasNoErrors();

        $s->refresh();
        $this->assertContains('held_for_pickup', $s->fieldNextStatuses());
        $this->assertContains('returned', $s->allowedNextStatuses());
        $this->update($s, ['status' => 'returned'])->assertSessionHasErrors('status');
        $this->update($s, ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame('delivery_attempted', $s->refresh()->status);
    }

    public function test_every_status_has_a_label_a_description_and_a_way_out_unless_final(): void
    {
        foreach (Shipment::STATUSES as $status) {
            $this->assertNotSame('', Shipment::description($status), $status);
            $s = new Shipment(['status' => $status]);
            $this->assertSame(in_array($status, Shipment::OPEN_STATUSES, true), $s->allowedNextStatuses() !== [], $status);
        }
        $this->assertSame('Returned to sender', Shipment::label('returned'));
    }
}
