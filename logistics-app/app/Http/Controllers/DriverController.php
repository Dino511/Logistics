<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Helper;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CrewAvailability;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index()
    {
        return view('drivers.index', [
            'drivers' => Driver::with('vehicle')->withCount('shipments')->orderBy('name')->get(),
            'helpers' => Helper::with(['vehicle', 'user'])->orderBy('name')->get(),
            // Drivers and helpers still out on a shipment that isn't finished yet.
            'busy' => app(CrewAvailability::class)->busy(),
        ]);
    }

    public function create()
    {
        return view('drivers.form', ['driver' => new Driver(['status' => 'active']), 'vehicles' => Vehicle::orderBy('plate_number')->get(), 'accounts' => $this->linkableAccounts()]);
    }

    public function store(Request $request)
    {
        $driver = Driver::create($this->validated($request));
        ActivityLog::record('created', "Added driver {$driver->name}", $driver);

        return redirect()->route('drivers.index')->with('status', "Driver {$driver->name} added.");
    }

    public function edit(Driver $driver)
    {
        return view('drivers.form', ['driver' => $driver, 'vehicles' => Vehicle::orderBy('plate_number')->get(), 'accounts' => $this->linkableAccounts($driver)]);
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

    /** Field Personnel drivers free to link: not linked yet, or already linked to this driver. */
    private function linkableAccounts(?Driver $driver = null)
    {
        return User::where('role', Role::FieldPersonnel->value)
            ->where('field_position', 'driver')
            ->where(fn ($q) => $q->whereDoesntHave('driver')->when($driver?->user_id, fn ($q) => $q->orWhere('id', $driver->user_id)))
            ->orderBy('name')
            ->get();
    }

    /**
     * +63 followed by exactly 10 digits, e.g. +639171234567 (13 characters total).
     * No spaces or dashes — that's what "strict" means here; the form's placeholder shows
     * the exact shape expected.
     */
    private const PHONE_REGEX = '/^\+63\d{10}$/';

    /**
     * Philippine LTO format: one uppercase letter + 2 digits, "-", 2 digits, "-", 6 digits
     * (e.g. N01-23-456789) — 13 characters including both hyphens.
     */
    private const LICENSE_REGEX = '/^[A-Z]\d{2}-\d{2}-\d{6}$/';

    private function validated(Request $request, ?Driver $driver = null): array
    {
        // Letters are case-insensitive to the driver typing them; the regex above requires
        // uppercase, so normalize here rather than reject "n01-23-456789" for a typo of case.
        $request->merge(['license_number' => strtoupper(trim((string) $request->input('license_number')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'license_number' => ['required', 'string', 'regex:'.self::LICENSE_REGEX, Rule::unique('drivers', 'license_number')->ignore($driver?->id)],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            // A Field Personnel account not already linked to another driver.
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::FieldPersonnel->value)->where('field_position', 'driver'), Rule::unique('drivers', 'user_id')->ignore($driver?->id)],
            'status' => ['required', Rule::in(Driver::STATUSES)],
        ], [
            'phone.regex' => 'Enter the phone number as +63 followed by 10 digits, e.g. +639171234567 (no spaces or dashes).',
            'user_id.exists' => 'Only a Field Personnel account whose position is Driver can be linked to a driver.',
            'user_id.unique' => 'That account is already linked to another driver.',
            'license_number.regex' => 'Enter the license number in LTO format A00-00-000000, e.g. N01-23-456789 (one letter, two digits, a dash, two digits, a dash, six digits).',
        ]);
    }
}
