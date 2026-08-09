<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan_id');

        return [
            'name'             => ['sometimes', 'string', 'max:100'],
            'slug'             => ['sometimes', 'string', 'max:100', Rule::unique('plans', 'slug')->ignore($planId), 'regex:/^[a-z0-9\-]+$/'],
            'description'      => ['nullable', 'string'],
            'price'            => ['sometimes', 'numeric', 'min:0'],
            'billing_interval' => ['sometimes', 'string', 'in:monthly,yearly'],
            'status'           => ['sometimes', 'string', 'max:30'],
        ];
    }
}
