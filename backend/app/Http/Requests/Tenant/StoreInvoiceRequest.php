<?php

namespace App\Http\Requests\Tenant;

use App\Models\Order;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'invoice_number' => ['required', 'string', 'max:100'],
            'subtotal'       => ['required', 'numeric', 'min:0'],
            'tax'            => ['sometimes', 'numeric', 'min:0'],
            'discount'       => ['sometimes', 'numeric', 'min:0'],
            'status'         => ['sometimes', 'string', 'max:30'],
            'issued_at'      => ['nullable', 'date'],
        ];
    }
}
