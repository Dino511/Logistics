<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the fleet (or the currently available part of it) cannot carry
 * a shipment's total weight, even after using every vehicle. Carries the
 * numbers needed to explain the shortfall to the user.
 */
class InsufficientFleetCapacityException extends RuntimeException
{
    public function __construct(
        public readonly float $requiredKg,
        public readonly float $availableCapacityKg,
        public readonly bool $onlyAvailableVehiclesConsidered = true,
    ) {
        $short = $requiredKg - $availableCapacityKg;

        $fleetNote = $onlyAvailableVehiclesConsidered
            ? 'Vehicles under Maintenance or already Dispatched were not counted.'
            : 'This exceeds the capacity of the entire fleet.';

        parent::__construct(sprintf(
            'Shipment needs %s kg but the fleet can carry %s kg (short by %s kg). %s',
            number_format($requiredKg, 2), number_format($availableCapacityKg, 2), number_format($short, 2), $fleetNote
        ));
    }
}
