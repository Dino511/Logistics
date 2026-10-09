<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehicleLease;
use App\Services\CrewAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index()
    {
        return view('vehicles.index', [
            'vehicles' => Vehicle::with('lease')->withCount(['drivers', 'shipments'])->orderBy('plate_number')->get(),
            // Vehicles still out on a shipment that isn't finished yet.
            'busy' => app(CrewAvailability::class)->busy(),
        ]);
    }

    public function create()
    {
        return view('vehicles.form', ['vehicle' => new Vehicle(['status' => 'available']), 'lease' => new VehicleLease]);
    }

    public function store(Request $request)
    {
        [$vehicleData, $leaseData] = $this->validated($request);

        $vehicle = DB::transaction(function () use ($vehicleData, $leaseData, $request) {
            $vehicle = Vehicle::create($vehicleData);

            if ($vehicleData['is_rented']) {
                $vehicle->lease()->create($this->withUploadedDocument($leaseData, $request));
            }

            return $vehicle;
        });

        ActivityLog::record('created', "Added vehicle {$vehicle->plate_number} ({$vehicle->type})".($vehicle->is_rented ? ' [rented]' : ''), $vehicle);

        // "Save & Add Another" keeps the fleet manager on a fresh Add Vehicle form instead of
        // bouncing them back to the list, for entering several vehicles in a row.
        if ($request->input('action') === 'save_and_add') {
            return redirect()->route('vehicles.create')->with('status', "Vehicle {$vehicle->plate_number} added. You may add another.");
        }

        return redirect()->route('vehicles.index')->with('status', "Vehicle {$vehicle->plate_number} added.");
    }

    public function edit(Vehicle $vehicle)
    {
        $vehicle->load('lease');

        return view('vehicles.form', ['vehicle' => $vehicle, 'lease' => $vehicle->lease ?? new VehicleLease]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        [$vehicleData, $leaseData] = $this->validated($request, $vehicle);

        DB::transaction(function () use ($vehicle, $vehicleData, $leaseData, $request) {
            $vehicle->update($vehicleData);

            if ($vehicleData['is_rented']) {
                $leaseData = $this->withUploadedDocument($leaseData, $request, $vehicle->lease);
                $vehicle->lease()->updateOrCreate(['vehicle_id' => $vehicle->id], $leaseData);
            }
            // Unchecking "rented" hides the lease section but deliberately does not delete the
            // lease record — it's kept as a contract history. Re-checking it brings the same
            // record back for editing. A dedicated "end this lease" action can delete it later.
        });

        ActivityLog::record('updated', "Updated vehicle {$vehicle->plate_number} (status: ".Vehicle::statusLabel($vehicle->status).')', $vehicle);

        return redirect()->route('vehicles.index')->with('status', "Vehicle {$vehicle->plate_number} updated.");
    }

    public function destroy(Vehicle $vehicle)
    {
        if ($vehicle->shipments()->exists()) {
            return back()->with('error', "{$vehicle->plate_number} is used by existing shipments. Set it to Maintenance instead of deleting it.");
        }

        DB::transaction(function () use ($vehicle) {
            if ($vehicle->lease?->document_path) {
                Storage::disk('public')->delete($vehicle->lease->document_path);
            }
            $vehicle->delete(); // drivers assigned to it are unassigned automatically (ON DELETE SET NULL); the lease row cascades.
        });

        ActivityLog::record('deleted', "Deleted vehicle {$vehicle->plate_number}", $vehicle);

        return back()->with('status', "Vehicle {$vehicle->plate_number} deleted.");
    }

    /**
     * Philippine plate formats: 3 letters + 3 digits (older/legacy stock) or 3 letters +
     * 4 digits (current standard), e.g. ABC123 or ABC1234. The input may be typed with or
     * without a space — that space is stripped before this ever runs (see below), so the
     * regex only has to check the canonical, no-space form.
     */
    private const PLATE_REGEX = '/^[A-Z]{3}\d{3,4}$/';

    /** @return array{0: array, 1: array} [vehicleData, leaseData] */
    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $request->merge([
            // Accept "ABC 1234", "abc1234", "  abc 1234  " etc., but always store and check
            // one canonical form — otherwise "ABC 1234" and "ABC1234" would pass the unique
            // check as two different plates when they're really the same one.
            'plate_number' => strtoupper(preg_replace('/\s+/', '', trim((string) $request->input('plate_number')))),
            'is_rented' => $request->boolean('is_rented'),
        ]);
        $isRented = $request->boolean('is_rented');

        $validator = Validator::make($request->all(), [
            'plate_number' => ['required', 'string', 'regex:'.self::PLATE_REGEX, Rule::unique('vehicles', 'plate_number')->ignore($vehicle?->id)],
            'type' => ['required', Rule::in(Vehicle::TYPES)],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
            'is_rented' => ['boolean'],

            // Lease fields are always just "nullable" here: whether they're actually required
            // depends on is_rented, which is cross-field logic Laravel's required_if handles
            // unreliably against a boolean (its "1" parameter only matches the literal string
            // "true", not "1", once the other field is a native bool) — so that's done
            // explicitly below in ->after() instead of relying on it.
            'lessor_name' => ['nullable', 'string', 'max:150'],
            'rate_type' => ['nullable', Rule::in(VehicleLease::RATE_TYPES)],
            'rate_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'start_date' => ['nullable', 'date'],
            // Whether end_date must be strictly after start_date (vs. the looser
            // after_or_equal it used to be) depends on whether either date is actually
            // changing — an untouched, already-saved pair must not break — so that
            // comparison happens explicitly below in ->after() instead of a static rule.
            'end_date' => ['nullable', 'date'],
            'lease_status' => ['nullable', Rule::in(VehicleLease::STATUSES)],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:8192'],
            'remove_document' => ['nullable', 'boolean'],
        ], [
            'plate_number.regex' => 'Enter a valid Philippine plate number, e.g. ABC 123 or ABC 1234 (3 letters followed by 3 or 4 numbers).',
        ]);

        $validator->after(function ($validator) use ($request, $isRented, $vehicle) {
            if (! $isRented) {
                return;
            }
            if (! $request->filled('lessor_name')) {
                $validator->errors()->add('lessor_name', 'Enter the lessor\'s name for a rented vehicle.');
            }
            if (! $request->filled('start_date')) {
                $validator->errors()->add('start_date', 'Enter the lease start date for a rented vehicle.');
            } elseif ($this->isNewOrChangedDate($request->input('start_date'), $vehicle?->lease?->start_date)
                && Carbon::parse($request->input('start_date'))->isPast() && ! Carbon::parse($request->input('start_date'))->isToday()) {
                // Only checked for a start date that's actually new or being changed — an
                // already-active lease legitimately has a start date in the past, and
                // an unrelated edit (e.g. changing the vehicle's status) must not break
                // just because that untouched date got resubmitted with the rest of the form.
                $validator->errors()->add('start_date', 'The lease start date can\'t be in the past.');
            }
            if (! $request->filled('lease_status')) {
                $validator->errors()->add('lease_status', 'Choose a contract status for a rented vehicle.');
            }
            // Only re-check the start/end pair when one of them is actually new or being
            // changed — an untouched historical pair (e.g. an old one-day lease where the
            // dates were equal, allowed under the old rule) must survive an unrelated edit.
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startChanged = $this->isNewOrChangedDate($request->input('start_date'), $vehicle?->lease?->start_date);
                $endChanged = $this->isNewOrChangedDate($request->input('end_date'), $vehicle?->lease?->end_date);
                if (($startChanged || $endChanged)
                    && Carbon::parse($request->input('end_date'))->lte(Carbon::parse($request->input('start_date')))) {
                    $validator->errors()->add('end_date', 'The lease end date must be after the start date (not the same day).');
                }
            }
            if (! $request->filled('rate_type')) {
                $validator->errors()->add('rate_type', 'Choose a rate frequency (daily, monthly, or annual) for a rented vehicle.');
            }
            if (! $request->filled('rate_amount')) {
                $validator->errors()->add('rate_amount', 'Enter the rate amount for a rented vehicle.');
            }
        });

        $data = $validator->validate();

        $vehicleData = [
            'plate_number' => $data['plate_number'],
            'type' => $data['type'],
            'capacity_kg' => $data['capacity_kg'] ?? null,
            'status' => $data['status'],
            'is_rented' => $isRented,
        ];

        $leaseData = [
            'lessor_name' => $data['lessor_name'] ?? null,
            'rate_type' => $data['rate_type'] ?? null,
            'rate_amount' => $data['rate_amount'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['lease_status'] ?? 'active',
        ];

        return [$vehicleData, $leaseData];
    }

    /** Adds document_path to $leaseData from the upload (or a removal), leaving it untouched otherwise. */
    private function withUploadedDocument(array $leaseData, Request $request, ?VehicleLease $existing = null): array
    {
        if ($request->hasFile('document')) {
            if ($existing?->document_path) {
                Storage::disk('public')->delete($existing->document_path);
            }
            $leaseData['document_path'] = $request->file('document')->store('lease-contracts', 'public');
        } elseif ($request->boolean('remove_document') && $existing?->document_path) {
            Storage::disk('public')->delete($existing->document_path);
            $leaseData['document_path'] = null;
        }

        return $leaseData;
    }

    /** True when $submitted (a "Y-m-d" string) is a new value or differs from the date already on file. */
    private function isNewOrChangedDate(string $submitted, ?Carbon $existing): bool
    {
        return $existing === null || ! $existing->isSameDay(Carbon::parse($submitted));
    }
}
