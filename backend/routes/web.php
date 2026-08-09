<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CafeRegistrationController;
use App\Http\Controllers\Tenant\CafeSettingsController;
use App\Http\Controllers\Tenant\StaffController;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::post('/register-cafe', [CafeRegistrationController::class, 'store'])->name('tenant.register');

Route::middleware(['auth:web', 'tenant'])->prefix('cafes/{cafe_slug}')->group(function () {
    Route::get('/dashboard', function () {
        $cafe = app(TenantContext::class)->getCafe();
        return response()->json([
            'message' => 'Tenant dashboard loaded.',
            'cafe' => [
                'id' => $cafe->id,
                'name' => $cafe->name,
                'slug' => $cafe->slug,
            ],
        ]);
    })->name('tenant.dashboard');

    Route::get('/settings', [CafeSettingsController::class, 'show'])->name('tenant.settings.show');
    Route::put('/settings', [CafeSettingsController::class, 'update'])->name('tenant.settings.update');

    Route::get('/branches', [BranchController::class, 'index'])->name('tenant.branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('tenant.branches.store');
    Route::put('/branches/{branch_id}', [BranchController::class, 'update'])->name('tenant.branches.update');

    Route::get('/staff', [StaffController::class, 'index'])->name('tenant.staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('tenant.staff.store');
    Route::put('/staff/{staff_id}', [StaffController::class, 'update'])->name('tenant.staff.update');
    Route::delete('/staff/{staff_id}', [StaffController::class, 'destroy'])->name('tenant.staff.destroy');
});

Route::middleware(['auth:web'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Super Admin access required.');
        }

        return response()->json([
            'message' => 'Super Admin platform dashboard loaded.',
            'user' => auth()->user()->only('id', 'name', 'email'),
        ]);
    })->name('admin.dashboard');
});
