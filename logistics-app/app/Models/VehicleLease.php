<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleLease extends Model
{
    public const STATUSES = ['active', 'expired', 'terminated'];

    public const RATE_TYPES = ['daily', 'monthly', 'annual'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function documentUrl(): ?string
    {
        return $this->document_path ? asset('storage/'.$this->document_path) : null;
    }

    public static function statusLabel(string $status): string
    {
        return ucfirst($status);
    }

    public static function statusBadge(string $status): string
    {
        return ['active' => 'b-delivered', 'expired' => 'b-inactive', 'terminated' => 'b-delayed'][$status] ?? 'b-pending';
    }

    private const RATE_SUFFIX = ['daily' => '/day', 'monthly' => '/mo', 'annual' => '/yr'];

    /** A short "₱X/day" (or /mo, /yr) style summary of the lease's single rate. */
    public function rateSummary(): string
    {
        if ($this->rate_amount === null || $this->rate_type === null) {
            return '—';
        }

        return '₱'.number_format((float) $this->rate_amount, 2).(self::RATE_SUFFIX[$this->rate_type] ?? '');
    }

    public static function rateTypeLabel(string $type): string
    {
        return ucfirst($type);
    }
}
