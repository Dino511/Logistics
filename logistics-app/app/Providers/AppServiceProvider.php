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
        $adminOrManager = fn (User $user) => $user->isSuperAdmin() || $user->hasRole('manager');

        // Administration. A Manager manages Field Personnel accounts only (UserController
        // enforces that part); Logistics Coordinators have no Administration pages.
        Gate::define('manage-users', $adminOrManager);
        // The Driver dashboard texts and Site Images stay with the Super Admin.
        Gate::define('manage-site-contents', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-site-images', fn (User $user) => $user->isSuperAdmin());
        // Insights: Managers may read the activity log; only a Super Admin prints or exports it.
        Gate::define('view-activity-logs', $adminOrManager);
        Gate::define('export-activity-logs', fn (User $user) => $user->isSuperAdmin());
    }
}
