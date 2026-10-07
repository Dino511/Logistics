<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ShipmentAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentNoteController extends Controller
{
    /** Office staff, and Field Personnel on their own shipments, can post. Everyone can read. */
    public static function canPost($user, Shipment $shipment): bool
    {
        return $user->hasRole('manager', 'logistics_coordinator')
            || ($user->hasRole('field_personnel') && $shipment->isAssignedToDriver($user->driver));
    }

    /**
     * Notes posted after the one with id `after`, rendered as list items, so an open page
     * can add them without reloading. Anyone signed in can read a shipment's notes.
     */
    public function index(Request $request, Shipment $shipment): JsonResponse
    {
        $after = (int) $request->query('after', 0);
        $notes = $shipment->shipmentNotes()->with('user.avatar')->where('id', '>', $after)->get();

        return response()->json([
            'html' => $notes->map(fn ($n) => view('shipments._note', ['n' => $n])->render())->implode(''),
            'last_id' => $notes->max('id') ?? $after,
        ]);
    }

    public function store(Request $request, Shipment $shipment, ShipmentAlerts $alerts)
    {
        abort_unless(self::canPost($request->user(), $shipment), 403);

        $data = $request->validateWithBag('note', ['body' => ['required', 'string', 'max:1000']], [
            'body.required' => __('Write a note before posting.'),
        ]);

        // created_at is set to the server's current time when the note is saved.
        $note = $shipment->shipmentNotes()->create(['user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $alerts->noteAdded($note);

        // Posted from the page without reloading: send back the new note to show.
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('shipments._note', ['n' => $note->load('user.avatar')])->render(),
                'id' => $note->id,
            ], 201);
        }

        return redirect(route('shipments.show', $shipment).'#notes');
    }
}
