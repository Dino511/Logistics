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
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());
        // Activity log history and printing: Super Admin and Manager only.
        Gate::define('view-activity-logs', fn (User $user) => $user->isSuperAdmin() || $user->hasRole('manager'));
    }
}
