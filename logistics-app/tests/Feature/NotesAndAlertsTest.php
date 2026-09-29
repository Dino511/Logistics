<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentAlerts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesCoreTables;
use Tests\TestCase;

class NotesAndAlertsTest extends TestCase
{
    use MigratesCoreTables;

    private User $coordinator;

    private User $field;

    private User $manager;

    private Driver $driver;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // The real tables come from SQL Server-only migrations; create the columns used here.
        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('name');
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
            $t->unsignedBigInteger('created_by')->nullable();
            $t->text('notes')->nullable(); // the real column; the thread relation must not clash with it
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

        $this->coordinator = $this->user(Role::LogisticsCoordinator);
        $this->manager = $this->user(Role::Manager);
        $this->field = $this->user(Role::FieldPersonnel);
        $this->driver = Driver::create(['name' => 'Felix Field', 'user_id' => $this->field->id]);
        $this->shipment = Shipment::forceCreate([
            'tracking_number' => 'SH-NOTE-1', 'status' => 'in_transit', 'destination_name' => 'Branch',
            'destination_city' => 'Cebu City', 'driver_id' => $this->driver->id, 'created_by' => $this->coordinator->id,
            'notes' => 'Fragile, keep upright',
        ]);
    }

    public function test_the_thread_and_the_shipments_own_notes_field_are_separate(): void
    {
        $this->note($this->coordinator, 'Gate code is 4412');

        $s = $this->shipment->fresh();
        $this->assertSame('Fragile, keep upright', $s->notes);
        $this->assertSame(['Gate code is 4412'], $s->shipmentNotes->pluck('body')->all());
    }

    private function note(User $as, string $body)
    {
        return $this->actingAs($as)->post("/shipments/{$this->shipment->shipment_id}/notes", ['body' => $body]);
    }

    private function alertsFor(User $user): array
    {
        return Alert::where('user_id', $user->id)->pluck('type')->all();
    }

    public function test_a_note_is_saved_and_alerts_the_other_people_involved(): void
    {
        $this->note($this->coordinator, 'Gate code is 4412')->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipment_notes', ['shipment_id' => $this->shipment->shipment_id, 'user_id' => $this->coordinator->id, 'body' => 'Gate code is 4412']);
        $this->assertSame(['note'], $this->alertsFor($this->field), 'the driver hears about it');
        $this->assertSame([], $this->alertsFor($this->coordinator), 'not the author');
        $this->assertSame([], $this->alertsFor($this->manager), 'not someone uninvolved');

        // A manager who joins the thread is now involved, and hears the driver's reply.
        $this->note($this->manager, 'Customer asked for after 2 PM');
        $this->note($this->field, 'OK, arriving 2:30');
        $this->assertSame(['note'], $this->alertsFor($this->manager));
        $this->assertSame(['note', 'note'], $this->alertsFor($this->coordinator));
    }

    public function test_who_can_post(): void
    {
        $this->note($this->field, 'On my way')->assertRedirect();

        $otherField = $this->user(Role::FieldPersonnel);
        $this->note($otherField, 'Not my delivery')->assertForbidden();

        $this->note($this->coordinator, '')->assertSessionHasErrors('body', null, 'note');
        $this->note($this->coordinator, str_repeat('x', 1001))->assertSessionHasErrors('body', null, 'note');

        $this->assertDatabaseCount('shipment_notes', 1);
    }

    public function test_a_delay_from_the_field_alerts_the_creator_and_all_managers(): void
    {
        $this->actingAs($this->field)->post("/shipments/{$this->shipment->shipment_id}/field-update", ['status' => 'delayed', 'note' => 'Road closed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['delayed'], $this->alertsFor($this->coordinator));
        $this->assertSame(['delayed'], $this->alertsFor($this->manager));
        $this->assertSame([], $this->alertsFor($this->field), 'not the driver who reported it');
        $this->assertStringContainsString('Road closed', Alert::where('user_id', $this->manager->id)->value('message'));
    }

    public function test_a_delivery_alerts_the_people_involved(): void
    {
        $this->actingAs($this->field)->post("/shipments/{$this->shipment->shipment_id}/field-update", [
            'status' => 'delivered', 'received_by' => 'Ana', 'proof_photo' => UploadedFile::fake()->image('pod.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(['delivered'], $this->alertsFor($this->coordinator));
        $this->assertSame([], $this->alertsFor($this->manager), 'deliveries are routine; only delays go to all managers');
    }

    public function test_the_bell_lists_only_my_alerts_and_can_mark_them_read(): void
    {
        $this->note($this->coordinator, 'Gate code is 4412');
        Alert::create(['user_id' => $this->manager->id, 'shipment_id' => $this->shipment->shipment_id, 'type' => 'note', 'message' => 'someone else']);

        $json = $this->actingAs($this->field)->getJson('/alerts')->assertOk()->json();
        $this->assertSame(1, $json['unread']);
        $this->assertCount(1, $json['alerts']);
        $this->assertStringContainsString('Gate code is 4412', $json['alerts'][0]['message']);

        // Opening it marks it read and goes to the shipment's notes.
        $this->get($json['alerts'][0]['url'])->assertRedirect(route('shipments.show', $this->shipment->shipment_id).'#notes');
        $this->assertSame(0, $this->getJson('/alerts')->json('unread'));

        // Someone else's alert can't be opened.
        $theirs = Alert::where('user_id', $this->manager->id)->first();
        $this->get("/alerts/{$theirs->id}")->assertNotFound();

        $this->note($this->coordinator, 'Another');
        $this->postJson('/alerts/read-all')->assertOk();
        $this->assertSame(0, $this->getJson('/alerts')->json('unread'));
        $this->assertNull($theirs->fresh()->read_at, "doesn't touch other people's alerts");
    }

    public function test_assigning_a_driver_on_dispatch_alerts_them(): void
    {
        $other = $this->user(Role::FieldPersonnel);
        $legDriver = Driver::create(['name' => 'Leg Driver', 'user_id' => $other->id]);

        app(ShipmentAlerts::class)->driversAssigned($this->shipment, [$this->driver->id, $legDriver->id, null], $this->coordinator->id);

        $this->assertSame(['assigned'], $this->alertsFor($this->field));
        $this->assertSame(['assigned'], $this->alertsFor($other));
    }
}
