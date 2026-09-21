<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case LogisticsCoordinator = 'logistics_coordinator';
    case FieldPersonnel = 'field_personnel';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::LogisticsCoordinator => 'Logistics Coordinator',
            self::FieldPersonnel => 'Field Personnel',
        };
    }
}
