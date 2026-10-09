<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    public function test_only_a_manager_cancels_and_a_coordinator_keeps_every_other_status(): void
    {
        $coordinator = $this->user(Role::LogisticsCoordinator);
        $s = $this->shipment();
        $status = fn (User $user, string $to) => $this->actingAs($user)->patch("/shipments/{$s->shipment_id}/status", ['status' => $to]);

        $status($coordinator, 'cancelled')->assertForbidden();
        $status($this->field, 'cancelled')->assertForbidden();
        $status($this->user(Role::SuperAdmin), 'cancelled')->assertForbidden();
        $this->assertSame('in_transit', $s->fresh()->status);

        $status($coordinator, 'delayed')->assertRedirect()->assertSessionHas('status');
        $this->assertSame('delayed', $s->fresh()->status);

        $status($this->user(Role::Manager), 'cancelled')->assertRedirect()->assertSessionHas('status');
        $this->assertSame('cancelled', $s->fresh()->status);
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

    /** A shipment with two pickup stops, assigned to this test's driver. */
    private function multiPickup(string $status): Shipment
    {
        $s = $this->shipment($status);
        foreach (['Main Store', 'Supplier A'] as $i => $name) {
            $s->pickups()->create(['sequence' => $i + 1, 'name' => $name, 'address' => 'Somewhere', 'city' => 'Manila']);
        }

        return $s;
    }

    public function test_ticking_off_every_pickup_moves_a_ready_shipment_to_picked_up(): void
    {
        $s = $this->multiPickup('ready_for_pickup');
        [$first, $second] = $s->pickups;

        // "Picked up" can't be claimed while stops are still to be collected.
        $this->update($s, ['status' => 'picked_up'])->assertSessionHasErrors('status');

        $this->actingAs($this->field)->post("/shipments/{$s->shipment_id}/pickups/{$first->id}/collect")->assertRedirect();
        $this->assertSame('ready_for_pickup', $s->refresh()->status);
        $this->assertNotNull($first->refresh()->picked_up_at);
        $this->assertSame($this->field->id, (int) $first->picked_up_by);

        $this->actingAs($this->field)->post("/shipments/{$s->shipment_id}/pickups/{$second->id}/collect")->assertRedirect();
        $this->assertSame('picked_up', $s->refresh()->status);
        $this->assertDatabaseHas('shipment_status_history', ['shipment_id' => $s->shipment_id, 'note' => 'Collected pickup 2 of 2: Supplier A']);
        $this->assertDatabaseHas('shipment_status_history', ['shipment_id' => $s->shipment_id, 'status' => 'picked_up', 'note' => 'All pickups collected.']);
    }

    public function test_a_delivery_cannot_be_finished_with_a_pickup_still_to_collect(): void
    {
        $s = $this->multiPickup('in_transit');
        $s->pickups[0]->update(['picked_up_at' => now()]);

        $this->update($s, [
            'status' => 'delivered', 'received_by' => 'Ana Reyes',
            'proof_photo' => UploadedFile::fake()->image('pod.jpg', 800, 600),
        ])->assertSessionHasErrors('status');
        $this->assertSame('in_transit', $s->refresh()->status);

        $this->actingAs($this->field)->post("/shipments/{$s->shipment_id}/pickups/{$s->pickups[1]->id}/collect");
        $this->assertSame('in_transit', $s->refresh()->status, 'already past Picked up, so the status is left alone');

        $this->update($s, [
            'status' => 'delivered', 'received_by' => 'Ana Reyes',
            'proof_photo' => UploadedFile::fake()->image('pod.jpg', 800, 600),
        ])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $s->refresh()->status);
    }

    public function test_pickups_can_only_be_ticked_by_the_assigned_driver_or_the_office_once_released(): void
    {
        $pending = $this->multiPickup('pending');
        $this->actingAs($this->field)->post("/shipments/{$pending->shipment_id}/pickups/{$pending->pickups[0]->id}/collect")->assertSessionHas('error');
        $this->assertNull($pending->pickups[0]->refresh()->picked_up_at);

        $s = $this->multiPickup('ready_for_pickup');
        $stranger = $this->user(Role::FieldPersonnel);
        $this->actingAs($stranger)->post("/shipments/{$s->shipment_id}/pickups/{$s->pickups[0]->id}/collect")->assertForbidden();

        // A stop belonging to a different shipment is not found under this one.
        $this->actingAs($this->field)->post("/shipments/{$s->shipment_id}/pickups/{$pending->pickups[0]->id}/collect")->assertNotFound();

        $this->actingAs($this->user(Role::LogisticsCoordinator))->post("/shipments/{$s->shipment_id}/pickups/{$s->pickups[0]->id}/collect")->assertRedirect();
        $this->assertNotNull($s->pickups[0]->refresh()->picked_up_at);
    }

    public function test_a_reminder_stays_on_every_page_until_the_delivery_is_finished(): void
    {
        $s = $this->shipment('out_for_delivery');
        $office = $this->user(Role::LogisticsCoordinator);

        // Shown to the office and to the assigned driver, on pages that have nothing to do with shipments.
        $this->actingAs($office)->get('/shipments')->assertOk()->assertSee('1 shipment is not delivered yet');
        // Never to a Super Admin, who doesn't work with shipments.
        $this->actingAs($this->user(Role::SuperAdmin))->get('/emergency-contacts')->assertOk()->assertDontSee('not delivered yet');
        $this->actingAs($this->user(Role::Manager))->get('/emergency-contacts')->assertOk()
            ->assertSee('1 shipment is not delivered yet')->assertSee($s->tracking_number)
            ->assertSee("/shipments/{$s->shipment_id}", false);
        $this->actingAs($this->field)->get('/shipments')->assertOk()->assertSee('1 shipment is not delivered yet');

        // Another driver has nothing of their own outstanding.
        $this->actingAs($this->user(Role::FieldPersonnel))->get('/shipments')->assertOk()->assertDontSee('is not delivered yet');

        // A second one switches to a count, linking to the filtered list.
        $this->shipment('pending');
        $this->actingAs($office)->get('/shipments?status=open')->assertOk()
            ->assertSee('2 shipments are not delivered yet')
            // ...and opens into a list with a row for each, linking to its own page.
            ->assertSee('id="openBanner"', false)
            ->assertSeeInOrder(['open-banner-list', "/shipments/{$s->shipment_id}\"", 'Out for delivery'], false);

        // Finished: Delivered, Returned or Cancelled all end the reminder.
        Shipment::query()->update(['status' => 'delivered']);
        $this->actingAs($office)->get('/shipments')->assertOk()->assertDontSee('not delivered yet</strong>', false);
    }

    public function test_the_list_can_be_filtered_to_unfinished_shipments(): void
    {
        $open = $this->shipment('delayed');
        $done = $this->shipment('delivered');

        $this->actingAs($this->user(Role::Manager))->get('/shipments?status=open')->assertOk()
            ->assertSee("/shipments/{$open->shipment_id}\"", false)
            ->assertDontSee("/shipments/{$done->shipment_id}\"", false);
    }

    public function test_the_list_can_be_filtered_to_this_week_month_or_year_from_one_dropdown(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00'); // a Wednesday; its week is Sun Oct 4 to Sat Oct 10

        $make = function (string $when) {
            $s = $this->shipment('delivered');
            $s->update(['scheduled_delivery_at' => $when]);

            return $s->shipment_id;
        };
        $thisWeek = $make('2026-10-09 14:00:00');
        $sunday = $make('2026-10-04 00:30:00');     // first minutes of this week
        $saturday = $make('2026-10-10 23:30:00');   // last minutes of this week
        $laterThisMonth = $make('2026-10-12 09:00:00');
        $lastWeek = $make('2026-10-03 23:00:00');   // last hour of last week, still this month
        $november = $make('2026-11-02 09:00:00');
        $lastYear = $make('2025-10-20 09:00:00');
        $manager = $this->user(Role::Manager);

        $shown = fn (string $query) => $this->actingAs($manager)->get('/shipments'.$query)->assertOk()
            ->viewData('shipments')->pluck('shipment_id')->sort()->values()->all();
        $all = [$thisWeek, $sunday, $saturday, $laterThisMonth, $lastWeek, $november, $lastYear];

        $this->assertSame($all, $shown(''));
        $this->assertSame([$thisWeek, $sunday, $saturday], $shown('?period=weekly'));
        $this->assertSame([$thisWeek, $sunday, $saturday, $laterThisMonth, $lastWeek], $shown('?period=monthly'));
        $this->assertSame([$thisWeek, $sunday, $saturday, $laterThisMonth, $lastWeek, $november], $shown('?period=yearly'));
        // Anything else, including the older style of value, is ignored instead of failing.
        $this->assertSame($all, $shown('?period=fortnight'));
        $this->assertSame($all, $shown('?period=month-2026-10'));
        $this->assertSame($all, $shown('?month=10&year=2026'));

        // One dropdown with just the three choices; the choice shows as selected and
        // rides along to the printable list.
        $this->actingAs($manager)->get('/shipments?period=weekly')
            ->assertSee('<option value="weekly" selected>Weekly</option>', false)
            ->assertSee('<option value="monthly" >Monthly</option>', false)
            ->assertSee('<option value="yearly" >Yearly</option>', false)
            ->assertDontSee('<optgroup', false)
            ->assertDontSee('name="month"', false)->assertDontSee('name="year"', false)
            ->assertSee('period=weekly', false);
        $this->actingAs($manager)->get('/shipments/print?period=weekly')->assertOk()
            ->assertSee('Scheduled: This week (Oct 4 – Oct 10, 2026)')->assertSee('3 shipments');
        $this->actingAs($manager)->get('/shipments/print?period=monthly')->assertOk()->assertSee('Scheduled: This month (October 2026)');
        $this->actingAs($manager)->get('/shipments/print?period=yearly')->assertOk()->assertSee('Scheduled: This year (2026)');
    }
}
