<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

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

    Route::get('/staff', function () {
        Gate::authorize('permission', 'staff.view');
        $cafe = app(TenantContext::class)->getCafe();
        return response()->json([
            'message' => 'Staff list loaded.',
            'cafe_id' => $cafe->id,
        ]);
    })->name('tenant.staff');
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
