<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentNoteController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\SiteImageController;
use App\Http\Controllers\SosController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Middleware\EnsureActive;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Legal pages: public, linked from the sign-in consent checkbox.
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

// Language switch (English / Tagalog), also usable on the login page.
Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:20,1')->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// AuthenticateSession signs a session out once the account's password has changed.
Route::middleware(['auth', AuthenticateSession::class, EnsureActive::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everyone signed in can view shipments.
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/print', [ShipmentController::class, 'printList'])->name('shipments.print-list');

    // Pickups and deliveries by day. Field Personnel see only their own (the controller scopes it).
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Read-only city/province/postal-code lookup for the shipment form's autocomplete.
    Route::get('/locations/search', [LocationController::class, 'search'])->name('locations.search');
    Route::get('/locations/detect', [LocationController::class, 'detect'])->name('locations.detect');

    // Creating and updating shipments, and managing the fleet and drivers:
    // Manager and Logistics Coordinator (Super Admin always passes).
    Route::middleware('role:manager,logistics_coordinator')->group(function () {
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.status');
        Route::post('/shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])->name('shipments.dispatch');

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
    Route::get('/shipments/{shipment}/print', [ShipmentController::class, 'print'])->name('shipments.print');

    // Field Personnel updating a delivery assigned to them (the controller checks the assignment).
    Route::post('/shipments/{shipment}/field-update', [ShipmentController::class, 'fieldUpdate'])->name('shipments.field-update');
    // Ticking off one pickup stop of a multi-pickup shipment (the controller checks who may).
    Route::post('/shipments/{shipment}/pickups/{pickup}/collect', [ShipmentController::class, 'collectPickup'])->name('shipments.pickups.collect');

    // Driver SOS: an urgent alert to the office (the controller checks the role).
    Route::post('/sos', [SosController::class, 'store'])->middleware('throttle:3,1')->name('sos.store');

    // Emergency contacts behind the drivers' Emergency button: Manager and Super Admin.
    Route::middleware('role:manager')->prefix('emergency-contacts')->name('emergency-contacts.')->group(function () {
        Route::get('/', [EmergencyContactController::class, 'index'])->name('index');
        Route::post('/', [EmergencyContactController::class, 'store'])->name('store');
        Route::put('/{contact}', [EmergencyContactController::class, 'update'])->name('update');
        Route::delete('/{contact}', [EmergencyContactController::class, 'destroy'])->name('destroy');
    });

    // Notes thread on a shipment (the controller checks who may post).
    Route::get('/shipments/{shipment}/notes', [ShipmentNoteController::class, 'index'])->name('shipments.notes.index');
    Route::post('/shipments/{shipment}/notes', [ShipmentNoteController::class, 'store'])->middleware('throttle:20,1')->name('shipments.notes.store');

    // The signed-in user's own alerts (the bell).
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/{alert}', [AlertController::class, 'open'])->name('alerts.open');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');

    // Location sharing from the driver's phone (the controller checks the assignment).
    Route::post('/shipments/{shipment}/tracking/sharing', [TrackingController::class, 'sharing'])->name('tracking.sharing');
    Route::post('/shipments/{shipment}/tracking/ping', [TrackingController::class, 'ping'])->middleware('throttle:10,1')->name('tracking.ping');

    // Live tracking map and the dashboard's shipment export: office roles.
    Route::middleware('role:manager,logistics_coordinator')->group(function () {
        Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');
        Route::get('/tracking', [TrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/positions', [TrackingController::class, 'positions'])->name('tracking.positions');
    });

    // Super Admin only. The middleware blocks other roles; the controller re-checks via a Gate.
    Route::middleware('role:super_admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::patch('/{user}/role', [UserController::class, 'updateRole'])->name('role');
        Route::patch('/{user}/position', [UserController::class, 'updatePosition'])->name('position');
        Route::patch('/{user}/active', [UserController::class, 'toggleActive'])->name('active');
    });

    // Texts on the drivers' dashboard. Super Admin only; the controller re-checks via a Gate.
    Route::middleware('role:super_admin')->prefix('driver-dashboard')->name('site-contents.')->group(function () {
        Route::get('/', [SiteContentController::class, 'index'])->name('index');
        Route::put('/{content}', [SiteContentController::class, 'update'])->name('update');
    });

    // Super Admin only. The middleware blocks other roles; the controller re-checks via a Gate.
    Route::middleware('role:super_admin')->prefix('site-images')->name('site-images.')->group(function () {
        Route::get('/', [SiteImageController::class, 'index'])->name('index');
        Route::post('/', [SiteImageController::class, 'store'])->name('store');
        Route::post('/avatars/{user}', [SiteImageController::class, 'storeAvatar'])->name('avatar.store');
        Route::delete('/avatars/{user}', [SiteImageController::class, 'destroyAvatar'])->name('avatar.destroy');
    });
});
