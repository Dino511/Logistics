<?php

namespace App\Support;

class Csv
{
    /**
     * A cell value that's safe to open in Excel: a value starting with =, +, -, @, tab or
     * carriage return is prefixed with ' so it's shown as text, not run as a formula
     * (e.g. =HYPERLINK(...) typed into a shipment or log entry).
     */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
