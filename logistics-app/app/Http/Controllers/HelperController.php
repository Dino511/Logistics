<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Helper;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Truck / cargo helpers. They are listed on the Drivers page (DriverController@index);
 * this controller only adds, edits and deletes them.
 */
class HelperController extends Controller
{
    /** Same shape as a driver's phone: +63 followed by exactly 10 digits. */
    private const PHONE_REGEX = '/^\+63\d{10}$/';

    public function create()
    {
        return view('helpers.form', ['helper' => new Helper(['status' => 'active']), 'vehicles' => Vehicle::orderBy('plate_number')->get(), 'accounts' => $this->linkableAccounts()]);
    }

    public function store(Request $request)
    {
        $helper = Helper::create($this->validated($request));
        ActivityLog::record('created', "Added helper {$helper->name}", $helper);

        return redirect()->route('drivers.index')->with('status', "Helper {$helper->name} added.");
    }

    public function edit(Helper $helper)
    {
        return view('helpers.form', ['helper' => $helper, 'vehicles' => Vehicle::orderBy('plate_number')->get(), 'accounts' => $this->linkableAccounts($helper)]);
    }

    public function update(Request $request, Helper $helper)
    {
        $helper->update($this->validated($request, $helper));
        ActivityLog::record('updated', "Updated helper {$helper->name} (status: ".Driver::statusLabel($helper->status).')', $helper);

        return redirect()->route('drivers.index')->with('status', "Helper {$helper->name} updated.");
    }

    public function destroy(Helper $helper)
    {
        $helper->delete();
        ActivityLog::record('deleted', "Deleted helper {$helper->name}", $helper);

        return back()->with('status', "Helper {$helper->name} deleted.");
    }

    /** Field Personnel helpers free to link: not linked yet, or already linked to this helper. */
    private function linkableAccounts(?Helper $helper = null)
    {
        $taken = Helper::whereNotNull('user_id')->when($helper?->id, fn ($q) => $q->where('id', '!=', $helper->id))->pluck('user_id');

        return User::where('role', Role::FieldPersonnel->value)
            ->where('field_position', 'helper')
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->get();
    }

    private function validated(Request $request, ?Helper $helper = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            // A Field Personnel account with the position Helper, not already linked to another helper.
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::FieldPersonnel->value)->where('field_position', 'helper'), Rule::unique('helpers', 'user_id')->ignore($helper?->id)],
            'status' => ['required', Rule::in(Driver::STATUSES)],
        ], [
            'phone.regex' => 'Enter the phone number as +63 followed by 10 digits, e.g. +639171234567 (no spaces or dashes).',
            'user_id.exists' => 'Only a Field Personnel account whose position is Helper can be linked to a helper.',
            'user_id.unique' => 'That account is already linked to another helper.',
        ]);
    }
}
