<?php

namespace App\Services\Fleet;

/** The result of running the allocation algorithm: one leg per truck needed. */
final class AllocationPlan
{
    /** @param  AllocationLeg[]  $legs  In dispatch order: fullest/largest vehicle first. */
    public function __construct(
        public readonly array $legs,
        public readonly float $totalWeightKg,
    ) {}

    public function vehicleCount(): int
    {
        return count($this->legs);
    }

    public function isSplit(): bool
    {
        return $this->vehicleCount() > 1;
    }
}
