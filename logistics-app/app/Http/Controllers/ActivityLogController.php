<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    private const PRINT_LIMIT = 2000;

    private const EXPORT_LIMIT = 20000;

    public function index(Request $request)
    {
        Gate::authorize('view-activity-logs');
        $filters = $this->filters($request);

        return view('reports.activity', [
            'logs' => $this->query($filters)->simplePaginate(25)->withQueryString(),
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function print(Request $request)
    {
        Gate::authorize('export-activity-logs');
        $filters = $this->filters($request);
        $query = $this->query($filters);

        $total = (clone $query)->count();

        ActivityLog::record('report', "Printed the activity log ({$total} matching entries)");

        return view('reports.activity_print', [
            'logs' => $query->limit(self::PRINT_LIMIT)->get(),
            'total' => $total,
            'truncated' => $total > self::PRINT_LIMIT,
            'limit' => self::PRINT_LIMIT,
            'filters' => $filters,
            'userName' => $filters['user'] ? User::whereKey($filters['user'])->value('name') : null,
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('export-activity-logs');
        $filters = $this->filters($request);
        $query = $this->query($filters);

        ActivityLog::record('report', 'Exported the activity log to CSV');

        $filename = 'activity-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, ['Timestamp', 'User', 'Role', 'Action', 'Description', 'IP address']);

            foreach ($query->limit(self::EXPORT_LIMIT)->cursor() as $log) {
                fputcsv($out, array_map([Csv::class, 'safe'], [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user_name,
                    $log->user_role,
                    ActivityLog::actionLabel($log->action),
                    $log->description,
                    $log->ip_address,
                ]));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'action' => ['nullable', Rule::in(array_keys(ActivityLog::ACTIONS))],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'action' => $data['action'] ?? null,
            'user' => $data['user'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'q' => trim($data['q'] ?? ''),
        ];
    }

    private function query(array $f): Builder
    {
        return ActivityLog::query()
            ->when($f['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($f['user'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['to'], fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$f['q'].'%')
                ->orWhere('user_name', 'like', '%'.$f['q'].'%')))
            ->orderByDesc('id');
    }
}
