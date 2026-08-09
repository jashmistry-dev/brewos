<?php

namespace App\Http\Requests\Tenant;

use App\Models\Order;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'order_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($cafeId) {
                    $orderExists = Order::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('cafe_id', $cafeId)
                        ->exists();

                    if (! $orderExists) {
                        $fail('The selected order is invalid or does not belong to this cafe.');
                    }
                },
            ],
            'amount'                => ['required', 'numeric', 'min:0'],
            'method'                => ['required', 'string', 'in:cash,upi,card'],
            'status'                => ['sometimes', 'string', 'max:30'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'paid_at'               => ['nullable', 'date'],
        ];
    }
}
