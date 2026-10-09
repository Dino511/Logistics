<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own alerts (the bell). */
class AlertController extends Controller
{
    /** Unread count and the latest alerts, for the bell's dropdown and its refresh. */
    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::where('user_id', $request->user()->id)
            ->latest('id')->take(15)->get()
            ->map(fn (Alert $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'ago' => $a->created_at->diffForHumans(),
                'read' => $a->read_at !== null,
                'url' => route('alerts.open', $a),
            ]);

        return response()->json([
            'unread' => Alert::where('user_id', $request->user()->id)->unread()->count(),
            'alerts' => $alerts,
        ]);
    }

    /** Mark as read and go to the shipment it's about. */
    public function open(Request $request, Alert $alert)
    {
        abort_unless((int) $alert->user_id === (int) $request->user()->id, 404);
        $alert->read_at ??= now();
        $alert->save();

        return $alert->shipment_id
            ? redirect(route('shipments.show', $alert->shipment_id).($alert->type === 'note' ? '#notes' : ''))
            : redirect()->route('dashboard');
    }

    public function readAll(Request $request): JsonResponse
    {
        Alert::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
