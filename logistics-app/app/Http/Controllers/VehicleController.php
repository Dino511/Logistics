<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index()
    {
        return view('vehicles.index', [
            'vehicles' => Vehicle::withCount(['drivers', 'shipments'])->orderBy('plate_number')->get(),
        ]);
    }

    public function create()
    {
        return view('vehicles.form', ['vehicle' => new Vehicle(['status' => 'available'])]);
    }

    public function store(Request $request)
    {
        $vehicle = Vehicle::create($this->validated($request));
        ActivityLog::record('created', "Added vehicle {$vehicle->plate_number} ({$vehicle->type})", $vehicle);

        return redirect()->route('vehicles.index')->with('status', "Vehicle {$vehicle->plate_number} added.");
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.form', ['vehicle' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($this->validated($request, $vehicle));
        ActivityLog::record('updated', "Updated vehicle {$vehicle->plate_number} (status: ".Vehicle::statusLabel($vehicle->status).')', $vehicle);

        return redirect()->route('vehicles.index')->with('status', "Vehicle {$vehicle->plate_number} updated.");
    }

    public function destroy(Vehicle $vehicle)
    {
        if ($vehicle->shipments()->exists()) {
            return back()->with('error', "{$vehicle->plate_number} is used by existing shipments. Set it to Maintenance instead of deleting it.");
        }

        $vehicle->delete(); // drivers assigned to it are unassigned automatically (ON DELETE SET NULL)
        ActivityLog::record('deleted', "Deleted vehicle {$vehicle->plate_number}", $vehicle);

        return back()->with('status', "Vehicle {$vehicle->plate_number} deleted.");
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $request->merge(['plate_number' => strtoupper(trim((string) $request->input('plate_number')))]);

        return $request->validate([
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_number')->ignore($vehicle?->id)],
            'type' => ['required', Rule::in(Vehicle::TYPES)],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
        ]);
    }
}
