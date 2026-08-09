<?php

use App\Http\Controllers\Admin\AdminCafeController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlanFeatureController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Public\PublicOrderController;
use App\Http\Controllers\Tenant\AnalyticsController;
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
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\TableController;
use App\Http\Controllers\Tenant\TenantSubscriptionController;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register-cafe', [CafeRegistrationController::class, 'create'])->name('tenant.register.show');
    Route::post('/register-cafe', [CafeRegistrationController::class, 'store'])->name('tenant.register');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

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

    // Phase 3B — Tenant Subscription Billing
    Route::get('/subscription', [TenantSubscriptionController::class, 'index'])->name('tenant.subscription.show');
    Route::post('/subscription/subscribe', [TenantSubscriptionController::class, 'subscribe'])->name('tenant.subscription.subscribe');
    Route::post('/subscription/cancel', [TenantSubscriptionController::class, 'cancel'])->name('tenant.subscription.cancel');

    // Phase 4A — Basic Reports
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('tenant.reports.sales');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('tenant.reports.revenue');
    Route::get('/reports/staff', [ReportController::class, 'staff'])->name('tenant.reports.staff');

    // Phase 4B — Advanced Analytics (Rate limited 120 req/min/user)
    Route::middleware(['throttle:120,1'])->group(function () {
        Route::get('/analytics/customers', [AnalyticsController::class, 'customers'])->name('tenant.analytics.customers');
        Route::get('/analytics/menu', [AnalyticsController::class, 'menu'])->name('tenant.analytics.menu');
        Route::get('/analytics/peak-hours', [AnalyticsController::class, 'peakHours'])->name('tenant.analytics.peak_hours');
    });
});

Route::middleware(['auth:web', 'super_admin', 'throttle:120,1'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // Cafe Management
    Route::get('/cafes', [AdminCafeController::class, 'index'])->name('admin.cafes.index');
    Route::get('/cafes/{cafe_id}', [AdminCafeController::class, 'show'])->name('admin.cafes.show');
    Route::patch('/cafes/{cafe_id}/status', [AdminCafeController::class, 'updateStatus'])->name('admin.cafes.update_status');
    Route::delete('/cafes/{cafe_id}', [AdminCafeController::class, 'destroy'])->name('admin.cafes.destroy');

    // Plan Management
    Route::get('/plans', [PlanController::class, 'index'])->name('admin.plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('admin.plans.store');
    Route::get('/plans/{plan_id}', [PlanController::class, 'show'])->name('admin.plans.show');
    Route::put('/plans/{plan_id}', [PlanController::class, 'update'])->name('admin.plans.update');
    Route::delete('/plans/{plan_id}', [PlanController::class, 'destroy'])->name('admin.plans.destroy');

    // Plan Feature Management
    Route::get('/plans/{plan_id}/features', [PlanFeatureController::class, 'index'])->name('admin.plan_features.index');
    Route::post('/plans/{plan_id}/features', [PlanFeatureController::class, 'store'])->name('admin.plan_features.store');
    Route::delete('/plans/{plan_id}/features/{feature_id}', [PlanFeatureController::class, 'destroy'])->name('admin.plan_features.destroy');

    // Subscription Management
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions.index');
    Route::get('/subscriptions/{subscription_id}', [SubscriptionController::class, 'show'])->name('admin.subscriptions.show');
    Route::post('/subscriptions/{subscription_id}/cancel', [SubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');

    // Audit Log Viewer
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit_logs.index');
    Route::get('/audit-logs/{audit_log_id}', [AuditLogController::class, 'show'])->name('admin.audit_logs.show');
});
