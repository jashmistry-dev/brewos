<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Public\PublicOrderController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CafeRegistrationController;
use App\Http\Controllers\Tenant\CafeSettingsController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\InvoiceSettingController;
use App\Http\Controllers\Tenant\KitchenDisplayController;
use App\Http\Controllers\Tenant\MenuItemController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\TableController;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::post('/register-cafe', [CafeRegistrationController::class, 'store'])->name('tenant.register');

// Public QR Ordering Endpoints (Unauthenticated, Rate-limited 60 requests/min/IP)
Route::middleware(['throttle:60,1'])->prefix('public/qr')->group(function () {
    Route::get('/{qr_token}/menu', [PublicOrderController::class, 'menu'])->name('public.qr.menu');
    Route::post('/{qr_token}/orders', [PublicOrderController::class, 'store'])->name('public.qr.orders');
});

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

    Route::get('/categories', [CategoryController::class, 'index'])->name('tenant.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('tenant.categories.store');
    Route::put('/categories/{category_id}', [CategoryController::class, 'update'])->name('tenant.categories.update');
    Route::delete('/categories/{category_id}', [CategoryController::class, 'destroy'])->name('tenant.categories.destroy');

    Route::get('/menu-items', [MenuItemController::class, 'index'])->name('tenant.menu_items.index');
    Route::post('/menu-items', [MenuItemController::class, 'store'])->name('tenant.menu_items.store');
    Route::put('/menu-items/{item_id}', [MenuItemController::class, 'update'])->name('tenant.menu_items.update');
    Route::patch('/menu-items/{item_id}/toggle-availability', [MenuItemController::class, 'toggleAvailability'])->name('tenant.menu_items.toggle_availability');
    Route::delete('/menu-items/{item_id}', [MenuItemController::class, 'destroy'])->name('tenant.menu_items.destroy');

    Route::get('/tables', [TableController::class, 'index'])->name('tenant.tables.index');
    Route::post('/tables', [TableController::class, 'store'])->name('tenant.tables.store');
    Route::put('/tables/{table_id}', [TableController::class, 'update'])->name('tenant.tables.update');
    Route::post('/tables/{table_id}/regenerate-qr', [TableController::class, 'regenerateQrToken'])->name('tenant.tables.regenerate_qr');
    Route::delete('/tables/{table_id}', [TableController::class, 'destroy'])->name('tenant.tables.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('tenant.orders.index');
    Route::get('/orders/{order_id}', [OrderController::class, 'show'])->name('tenant.orders.show');
    Route::patch('/orders/{order_id}/status', [OrderController::class, 'updateStatus'])->name('tenant.orders.update_status');

    Route::get('/kitchen-display', [KitchenDisplayController::class, 'index'])->name('tenant.kitchen_display.index');

    // Phase 2D — Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('tenant.payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('tenant.payments.store');

    // Phase 2D — Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('tenant.invoices.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('tenant.invoices.store');
    Route::get('/invoices/{invoice_id}', [InvoiceController::class, 'show'])->name('tenant.invoices.show');
    Route::get('/invoices/{invoice_id}/download', [InvoiceController::class, 'download'])->name('tenant.invoices.download');

    // Phase 2D — Invoice Settings
    Route::get('/invoice-settings', [InvoiceSettingController::class, 'show'])->name('tenant.invoice_settings.show');
    Route::put('/invoice-settings', [InvoiceSettingController::class, 'update'])->name('tenant.invoice_settings.update');
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
