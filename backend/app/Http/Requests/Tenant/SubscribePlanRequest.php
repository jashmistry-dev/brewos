<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SubscribePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id'                  => ['required', 'integer', 'exists:plans,id'],
            'provider'                 => ['nullable', 'string', 'max:50'],
            'provider_subscription_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
