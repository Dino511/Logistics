<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index()
    {
        return view('drivers.index', [
            'drivers' => Driver::with('vehicle')->withCount('shipments')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('drivers.form', ['driver' => new Driver(['status' => 'active']), 'vehicles' => Vehicle::orderBy('plate_number')->get()]);
    }

    public function store(Request $request)
    {
        $driver = Driver::create($this->validated($request));
        ActivityLog::record('created', "Added driver {$driver->name}", $driver);

        return redirect()->route('drivers.index')->with('status', "Driver {$driver->name} added.");
    }

    public function edit(Driver $driver)
    {
        return view('drivers.form', ['driver' => $driver, 'vehicles' => Vehicle::orderBy('plate_number')->get()]);
    }

    public function update(Request $request, Driver $driver)
    {
        $driver->update($this->validated($request, $driver));
        ActivityLog::record('updated', "Updated driver {$driver->name} (status: ".Driver::statusLabel($driver->status).')', $driver);

        return redirect()->route('drivers.index')->with('status', "Driver {$driver->name} updated.");
    }

    public function destroy(Driver $driver)
    {
        if ($driver->shipments()->exists()) {
            return back()->with('error', "{$driver->name} has existing shipments. Set the driver to Inactive instead of deleting.");
        }

        $driver->delete();
        ActivityLog::record('deleted', "Deleted driver {$driver->name}", $driver);

        return back()->with('status', "Driver {$driver->name} deleted.");
    }

    private function validated(Request $request, ?Driver $driver = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'license_number' => ['required', 'string', 'max:50', Rule::unique('drivers', 'license_number')->ignore($driver?->id)],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'status' => ['required', Rule::in(Driver::STATUSES)],
        ]);
    }
}
