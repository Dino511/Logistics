<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $office = fn (User $user) => $user->hasRole('manager', 'logistics_coordinator');

        // Administration. Managers and Coordinators manage every account except Super Admins
        // (UserController enforces that part); the Driver dashboard texts and Site Images
        // stay with the Super Admin.
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin() || $office($user));
        Gate::define('manage-site-contents', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-site-images', fn (User $user) => $user->isSuperAdmin());
        // Insights: the activity log, its print-out and export.
        Gate::define('view-activity-logs', fn (User $user) => $user->isSuperAdmin());
    }
}
