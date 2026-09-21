<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Middleware\EnsureActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', EnsureActive::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everyone signed in can view shipments.
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');

    // Creating and updating shipments, and managing the fleet and drivers:
    // Manager and Logistics Coordinator (Super Admin always passes).
    Route::middleware('role:manager,logistics_coordinator')->group(function () {
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.status');

        Route::resource('vehicles', VehicleController::class)->except(['show', 'destroy']);
        Route::resource('drivers', DriverController::class)->except(['show', 'destroy']);
    });

    // Reports: Manager and Super Admin only (the controller re-checks with a Gate).
    Route::middleware('role:manager')->prefix('reports/activity-logs')->name('reports.activity')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('');
        Route::get('/print', [ActivityLogController::class, 'print'])->name('.print');
        Route::get('/export', [ActivityLogController::class, 'export'])->name('.export');
    });

    // Deleting fleet records is limited to Manager (and Super Admin).
    Route::middleware('role:manager')->group(function () {
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');
    });

    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');

    // Super Admin only. The middleware blocks other roles; the controller re-checks via a Gate.
    Route::middleware('role:super_admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::patch('/{user}/role', [UserController::class, 'updateRole'])->name('role');
        Route::patch('/{user}/active', [UserController::class, 'toggleActive'])->name('active');
    });
});
