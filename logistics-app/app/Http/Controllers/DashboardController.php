<?php

namespace App\Http\Controllers;

use App\Models\Inventory\Product;
use App\Models\Inventory\Stock;
use App\Models\Inventory\StockTransfer;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $open = Shipment::whereIn('status', ['pending', 'in_transit', 'delayed'])->count();
        $deliveredToday = Shipment::whereDate('actual_delivery_at', today())->count();
        $delayed = Shipment::where('status', 'delayed')->count();
        $inTransit = Shipment::where('status', 'in_transit')->count();

        $recent = Shipment::where('actual_delivery_at', '>=', now()->subDays(30))->selectRaw(
            "SUM(CASE WHEN delivery_result = 'on_time' THEN 1 ELSE 0 END) AS on_time, COUNT(*) AS total"
        )->first();
        $onTimeRate = $recent && $recent->total > 0 ? round($recent->on_time / $recent->total * 100).'%' : '—';

        $stats = [
            ['label' => 'Active shipments', 'value' => $open, 'change' => 'pending, in transit or delayed', 'up' => true],
            ['label' => 'Delivered today', 'value' => $deliveredToday, 'change' => 'since midnight', 'up' => true],
            ['label' => 'Delayed', 'value' => $delayed, 'change' => 'need attention', 'up' => $delayed === 0],
            ['label' => 'In transit', 'value' => $inTransit, 'change' => 'on the road', 'up' => true],
            ['label' => 'On-time rate', 'value' => $onTimeRate, 'change' => 'last 30 days', 'up' => true],
        ];

        // Deliveries per day for the last 7 days.
        $days = collect(range(6, 0))->map(fn ($n) => now()->subDays($n)->startOfDay());
        $delivered = Shipment::where('actual_delivery_at', '>=', $days->first())->pluck('actual_delivery_at');
        $weekly = [
            'labels' => $days->map(fn ($d) => $d->format('D'))->all(),
            'delivered' => $days->map(fn ($d) => $delivered->filter(fn ($t) => $t->isSameDay($d))->count())->all(),
        ];

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
                        'code' => $s->product->code,
                        'location' => $s->location->name,
                        'quantity' => $s->quantity,
                        'reorder' => $s->product->reorder_point,
                    ])->all(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return view('dashboard', compact('stats', 'weekly', 'shipments', 'inventory'));
    }
}
