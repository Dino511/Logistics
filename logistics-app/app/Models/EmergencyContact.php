<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    /** Category => label, in the order the Emergency list shows them. */
    public const CATEGORIES = [
        'emergency' => 'Emergency',
        'company' => 'Company',
        'roadside' => 'Roadside assistance',
        'medical' => 'Medical',
        'other' => 'Other',
    ];

    protected $fillable = ['name', 'phone', 'category', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** Contacts grouped by category, in display order. */
    public static function grouped()
    {
        $order = array_flip(array_keys(self::CATEGORIES));

        return static::orderBy('sort_order')->orderBy('name')->get()
            ->sortBy(fn ($c) => $order[$c->category] ?? 99)
            ->groupBy('category');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /** The number as a tel: link: digits and a leading + only. */
    public function telHref(): string
    {
        return 'tel:'.self::dialable($this->phone);
    }

    public static function dialable(?string $phone): string
    {
        return preg_replace('/(?!^\+)[^\d]/', '', trim((string) $phone));
    }

    /**
     * Whether a number is a valid Philippine number, ignoring spaces, dashes and brackets:
     *  - mobile:    09XX XXX XXXX, or +63 9XX XXX XXXX (10 digits after +63)
     *  - landline:  0 + area code + number = 10 digits, e.g. (02) 8123-4567, or +63 without the 0
     *  - hotline:   3 to 5 digits not starting with 0, e.g. 911, 143, 8888
     *  - toll-free: 1800 + 7 to 9 digits, e.g. 1-800-10-123-4567
     */
    public static function isValidPhilippineNumber(?string $phone): bool
    {
        $n = self::dialable($phone);

        return (bool) preg_match('/^(?:09\d{9}|\+639\d{9}|0[2-8]\d{8}|\+63[2-8]\d{8}|[1-9]\d{2,4}|1800\d{7,9})$/', $n);
    }
}
