<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Managers and Super Admins maintain the numbers behind the drivers' Emergency button. */
class EmergencyContactController extends Controller
{
    public function index()
    {
        return view('emergency-contacts.index', [
            'contacts' => EmergencyContact::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $contact = EmergencyContact::create($this->validated($request));
        ActivityLog::record('created', "Added emergency contact {$contact->name} ({$contact->phone})", $contact);

        return back()->with('status', "{$contact->name} added.");
    }

    public function update(Request $request, EmergencyContact $contact)
    {
        $contact->update($this->validated($request));
        ActivityLog::record('updated', "Updated emergency contact {$contact->name} ({$contact->phone})", $contact);

        return back()->with('status', "{$contact->name} updated.");
    }

    public function destroy(EmergencyContact $contact)
    {
        $contact->delete();
        ActivityLog::record('deleted', "Removed emergency contact {$contact->name} ({$contact->phone})", $contact);

        return back()->with('status', "{$contact->name} removed.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // Philippine formats only; spaces, dashes and brackets are allowed for readability.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[\d\s()\-]+$/', function ($attribute, $value, $fail) {
                if (! EmergencyContact::isValidPhilippineNumber($value)) {
                    $fail('Enter a valid Philippine number: a mobile like 0917 123 4567 or +63 917 123 4567, a landline like (02) 8123-4567, or a hotline like 911.');
                }
            }],
            'category' => ['required', Rule::in(array_keys(EmergencyContact::CATEGORIES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'phone.regex' => 'Use only digits, spaces, dashes, brackets and a leading +, e.g. 0917 123 4567.',
            'phone.max' => 'That number is too long for a Philippine number.',
        ]) + ['sort_order' => 0];
    }
}
