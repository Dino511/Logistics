<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Inventory\Product;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockTransfer;
use App\Models\Shipment;
use App\Models\SiteContent;
use App\Models\User;
use App\Models\VehicleLocationPing;
use App\Services\StockReservations;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Field Personnel get their own dashboard: their deliveries and guidance, not company analytics.
        if ($request->user()->hasRole('field_personnel')) {
            return $this->fieldDashboard($request->user());
        }

        $range = $this->range($request);

        $open = Shipment::whereIn('status', StockReservations::HOLDING_STATUSES)->count();
        $deliveredToday = Shipment::whereDate('actual_delivery_at', today())->count();
        $delayed = Shipment::whereIn('status', Shipment::PROBLEM_STATUSES)->count();
        $inTransit = Shipment::whereIn('status', array_diff(Shipment::ON_ROAD_STATUSES, Shipment::PROBLEM_STATUSES))->count();

        $recent = Shipment::where('actual_delivery_at', '>=', now()->subDays($range))->selectRaw(
            "SUM(CASE WHEN delivery_result = 'on_time' THEN 1 ELSE 0 END) AS on_time, COUNT(*) AS total"
        )->first();
        $onTimeRate = $recent && $recent->total > 0 ? round($recent->on_time / $recent->total * 100).'%' : '—';

        // 'tone' colours the card: good, warn (needs attention) or neutral.
        $stats = [
            ['label' => 'Active shipments', 'value' => $open, 'change' => 'not delivered yet', 'tone' => 'neutral', 'icon' => '📦'],
            ['label' => 'In transit', 'value' => $inTransit, 'change' => 'on the road', 'tone' => 'neutral', 'icon' => '🚚'],
            ['label' => 'Delivered today', 'value' => $deliveredToday, 'change' => 'since midnight', 'tone' => 'good', 'icon' => '✅'],
            ['label' => 'Delayed or failed', 'value' => $delayed, 'change' => $delayed ? 'need attention' : 'none right now', 'tone' => $delayed ? 'warn' : 'good', 'icon' => '⏱️'],
            ['label' => 'On-time rate', 'value' => $onTimeRate, 'change' => "last {$range} days", 'tone' => 'neutral', 'icon' => '🎯'],
        ];

        // Deliveries per day over the chosen period.
        $days = collect(range($range - 1, 0))->map(fn ($n) => now()->subDays($n)->startOfDay());
        $delivered = Shipment::where('actual_delivery_at', '>=', $days->first())->pluck('actual_delivery_at');
        $weekly = [
            'labels' => $days->map(fn ($d) => $d->format($range > 7 ? 'M j' : 'D'))->all(),
            'delivered' => $days->map(fn ($d) => $delivered->filter(fn ($t) => $t->isSameDay($d))->count())->all(),
        ];

        // Delayed or a failed delivery attempt, or past their scheduled time and still not delivered.
        $attention = Shipment::with('driver')
            ->where(fn ($q) => $q->whereIn('status', Shipment::PROBLEM_STATUSES)
                ->orWhere(fn ($q) => $q->whereIn('status', Shipment::OPEN_STATUSES)->where('scheduled_delivery_at', '<', now())))
            ->orderBy('scheduled_delivery_at')
            ->limit(6)
            ->get();

        $shipments = Shipment::with('driver')->orderByDesc('shipment_id')->limit(5)->get()->map(fn ($s) => [
            'id' => $s->tracking_number,
            'url' => route('shipments.show', $s),
            'origin' => $s->origin_city,
            'destination' => $s->destination_city,
            'driver' => $s->driver?->name ?? 'Unassigned',
            'status' => $s->statusLabel(),
            'class' => $s->badgeClass(),
            'eta' => $s->status === 'delivered' ? 'Delivered' : $s->scheduled_delivery_at->format('M j, g:i A'),
        ])->all();

        // Live data from the Inventory system. If that database is unreachable,
        // the dashboard still loads and the inventory section is hidden.
        $inventory = null;
        try {
            $inventory = Cache::remember('dashboard.inventory', 60, fn () => [
                'stats' => [
                    ['label' => 'Low stock items', 'value' => Stock::low()->count(), 'change' => 'at/below reorder point', 'up' => false],
                    ['label' => 'Pending transfers', 'value' => StockTransfer::pending()->count(), 'change' => 'awaiting receipt', 'up' => true],
                    ['label' => 'Active products', 'value' => Product::active()->count(), 'change' => 'in catalog', 'up' => true],
                ],
                'lowStock' => Stock::low()->with(['product', 'location'])->orderBy('inventories.quantity')->limit(8)->get()
                    ->map(fn ($s) => [
                        'product' => $s->product->name,
                        'code' => $s->product->sku,
                        'location' => $s->location->name,
                        'quantity' => $s->quantity,
                        'reorder' => $s->product->reorder_point,
                    ])->all(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return view('dashboard', compact('stats', 'weekly', 'shipments', 'inventory', 'attention', 'range'));
    }

    /** CSV of shipments created in the chosen period (office roles; see routes). */
    public function export(Request $request)
    {
        $range = $this->range($request);
        ActivityLog::record('report', "Exported shipments of the last {$range} days to CSV");

        $rows = Shipment::with(['driver', 'vehicle'])->where('created_at', '>=', now()->subDays($range))->orderByDesc('shipment_id');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tracking no.', 'Status', 'Origin', 'Destination', 'Driver', 'Vehicle', 'Scheduled', 'Dispatched', 'Delivered', 'Result', 'Created']);
            foreach ($rows->cursor() as $s) {
                fputcsv($out, array_map([Csv::class, 'safe'], [
                    $s->tracking_number, $s->statusLabel(), $s->origin_city, $s->destination_name.', '.$s->destination_city,
                    $s->driver?->name, $s->vehicle?->plate_number,
                    $s->scheduled_delivery_at?->format('Y-m-d H:i'), $s->dispatched_at?->format('Y-m-d H:i'),
                    $s->actual_delivery_at?->format('Y-m-d H:i'), $s->delivery_result, $s->created_at?->format('Y-m-d H:i'),
                ]));
            }
            fclose($out);
        }, 'shipments-last-'.$range.'-days-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** The chosen period in days: 7 (default) or 30. */
    private function range(Request $request): int
    {
        return (int) $request->query('range') === 30 ? 30 : 7;
    }

    /** Only this person's own data: their open deliveries, unread alerts and week. */
    private function fieldDashboard(User $user)
    {
        $driver = $user->driver;
        $deliveries = collect();
        $week = null;

        if ($driver) {
            // Problems first (they need attention), then by when they're due.
            $deliveries = Shipment::assignedToDriver($driver->id)
                ->whereIn('status', StockReservations::HOLDING_STATUSES)
                ->orderBy('scheduled_delivery_at')
                ->get()
                ->sortBy(fn ($s) => in_array($s->status, Shipment::PROBLEM_STATUSES, true) ? 0 : 1)
                ->values();

            $done = Shipment::assignedToDriver($driver->id)
                ->where('actual_delivery_at', '>=', now()->startOfWeek())
                ->get(['shipment_id', 'delivery_result']);
            $week = [
                'delivered' => $done->count(),
                'on_time' => $done->count() ? round($done->where('delivery_result', 'on_time')->count() / $done->count() * 100) : null,
                'delayed' => Shipment::assignedToDriver($driver->id)->whereIn('status', Shipment::PROBLEM_STATUSES)->count(),
            ];
        }

        $next = $deliveries->first();

        return view('dashboard-field', [
            'user' => $user,
            'driver' => $driver,
            // In the user's language: "Good morning" / "Magandang umaga" (lang/tl.json).
            'greeting' => __(match (true) {
                now()->hour < 12 => 'Good morning',
                now()->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            }),
            'next' => $next,
            'others' => $deliveries->slice(1)->values(),
            'dueToday' => $deliveries->filter(fn ($s) => $s->scheduled_delivery_at?->isToday())->count(),
            'week' => $week,
            'isSharing' => $next && VehicleLocationPing::where('shipment_id', $next->shipment_id)->where('driver_id', $driver->id)
                ->where('recorded_at', '>=', now()->subMinutes(VehicleLocationPing::LIVE_MINUTES))->exists(),
            'unread' => Alert::where('user_id', $user->id)->unread()->count(),
            'sections' => SiteContent::sections(),
        ]);
    }
}
