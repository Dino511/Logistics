<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Super Admin', 'admin@logistics.test', Role::SuperAdmin],
            ['Maria Manager', 'manager@logistics.test', Role::Manager],
            ['Carlo Coordinator', 'coordinator@logistics.test', Role::LogisticsCoordinator],
            ['Felix Field', 'field@logistics.test', Role::FieldPersonnel],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'role' => $role,
            ]);
        }
    }
}
