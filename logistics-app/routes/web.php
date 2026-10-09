<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\HelperController;
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
//
// Who can open what:
//   Super Admin ............ Insights (reports) and Administration, nothing else.
//   Manager ................ Operations, Fleet, Reports (read only), Users & Roles for
//                            Field Personnel accounts, and Emergency contacts.
//   Logistics Coordinator .. Operations and Fleet.
//   Field Personnel ........ the deliveries assigned to them, and their calendar.
// The `role` middleware lets in exactly the roles it names; no role passes automatically.
Route::middleware(['auth', AuthenticateSession::class, EnsureActive::class])->group(function () {
    // Home. Field Personnel get their own dashboard; a Super Admin is sent on to Reports.
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // The signed-in user's own alerts (the bell).
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/{alert}', [AlertController::class, 'open'])->name('alerts.open');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');

    // Creating and updating shipments, and managing the fleet: Manager and Logistics Coordinator.
    Route::middleware('role:manager,logistics_coordinator')->group(function () {
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.status');
        // Changing the driver, vehicle or helper of a shipment after it was created.
        Route::patch('/shipments/{shipment}/crew', [ShipmentController::class, 'updateCrew'])->name('shipments.crew');

        Route::resource('vehicles', VehicleController::class)->except(['show', 'destroy']);
        Route::resource('drivers', DriverController::class)->except(['show', 'destroy']);
        // Truck / cargo helpers are listed on the Drivers page, so there is no index of their own.
        Route::resource('helpers', HelperController::class)->except(['index', 'show', 'destroy']);

        // Live tracking map.
        Route::get('/tracking', [TrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/positions', [TrackingController::class, 'positions'])->name('tracking.positions');
    });

    // Deleting fleet records is limited to Managers.
    Route::middleware('role:manager')->group(function () {
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');
        Route::delete('/helpers/{helper}', [HelperController::class, 'destroy'])->name('helpers.destroy');
    });

    // Operations pages shared by the office and Field Personnel.
    Route::middleware('role:manager,logistics_coordinator,field_personnel')->group(function () {
        // Field Personnel see only the shipments assigned to them, on every page in this group
        // (the controllers scope it).
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/print', [ShipmentController::class, 'printList'])->name('shipments.print-list');

        // Pickups and deliveries by day.
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

        // Read-only city/province/postal-code lookup for the shipment form's autocomplete.
        Route::get('/locations/search', [LocationController::class, 'search'])->name('locations.search');
        Route::get('/locations/detect', [LocationController::class, 'detect'])->name('locations.detect');

        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        Route::get('/shipments/{shipment}/print', [ShipmentController::class, 'print'])->name('shipments.print');

        // Field Personnel updating a delivery assigned to them (the controller checks the assignment).
        Route::post('/shipments/{shipment}/field-update', [ShipmentController::class, 'fieldUpdate'])->name('shipments.field-update');
        // Ticking off one pickup stop of a multi-pickup shipment (the controller checks who may).
        Route::post('/shipments/{shipment}/pickups/{pickup}/collect', [ShipmentController::class, 'collectPickup'])->name('shipments.pickups.collect');

        // Driver SOS: an urgent alert to the office (the controller checks the role).
        Route::post('/sos', [SosController::class, 'store'])->middleware('throttle:3,1')->name('sos.store');

        // Notes thread on a shipment (the controller checks who may post).
        Route::get('/shipments/{shipment}/notes', [ShipmentNoteController::class, 'index'])->name('shipments.notes.index');
        Route::post('/shipments/{shipment}/notes', [ShipmentNoteController::class, 'store'])->middleware('throttle:20,1')->name('shipments.notes.store');

        // Location sharing from the driver's phone (the controller checks the assignment).
        Route::post('/shipments/{shipment}/tracking/sharing', [TrackingController::class, 'sharing'])->name('tracking.sharing');
        Route::post('/shipments/{shipment}/tracking/ping', [TrackingController::class, 'ping'])->middleware('throttle:10,1')->name('tracking.ping');
    });

    // Insights. Reports (the activity log): Super Admins, and Managers to read only.
    // Printing and exporting it stay with the Super Admin. The controller re-checks with Gates.
    Route::prefix('reports/activity-logs')->name('reports.activity')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->middleware('role:super_admin,manager')->name('');
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/print', [ActivityLogController::class, 'print'])->name('.print');
            Route::get('/export', [ActivityLogController::class, 'export'])->name('.export');
        });
    });

    // Administration: Super Admin and Manager. The controllers re-check with a Gate, and a
    // Manager can only see and change Field Personnel accounts.
    Route::middleware('role:super_admin,manager')->group(function () {
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::patch('/{user}/role', [UserController::class, 'updateRole'])->name('role');
            Route::patch('/{user}/position', [UserController::class, 'updatePosition'])->name('position');
            Route::patch('/{user}/active', [UserController::class, 'toggleActive'])->name('active');
        });

        // Emergency contacts behind the drivers' Emergency button.
        Route::prefix('emergency-contacts')->name('emergency-contacts.')->group(function () {
            Route::get('/', [EmergencyContactController::class, 'index'])->name('index');
            Route::post('/', [EmergencyContactController::class, 'store'])->name('store');
            Route::put('/{contact}', [EmergencyContactController::class, 'update'])->name('update');
            Route::delete('/{contact}', [EmergencyContactController::class, 'destroy'])->name('destroy');
        });
    });

    // Driver dashboard texts and Site Images: Super Admin only. The controllers re-check via a Gate.
    Route::middleware('role:super_admin')->group(function () {
        Route::prefix('driver-dashboard')->name('site-contents.')->group(function () {
            Route::get('/', [SiteContentController::class, 'index'])->name('index');
            Route::put('/{content}', [SiteContentController::class, 'update'])->name('update');
        });

        Route::prefix('site-images')->name('site-images.')->group(function () {
            Route::get('/', [SiteImageController::class, 'index'])->name('index');
            Route::post('/', [SiteImageController::class, 'store'])->name('store');
            Route::post('/avatars/{user}', [SiteImageController::class, 'storeAvatar'])->name('avatar.store');
            Route::delete('/avatars/{user}', [SiteImageController::class, 'destroyAvatar'])->name('avatar.destroy');
        });
    });
});
