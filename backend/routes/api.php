<?php

use App\Http\Controllers\Api\BillingWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function () {
    Route::post('/billing/{provider}', [BillingWebhookController::class, 'handle'])->name('api.webhooks.billing');
});
