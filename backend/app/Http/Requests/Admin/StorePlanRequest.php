<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route-group middleware enforces Super Admin.
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:100'],
            'slug'             => ['required', 'string', 'max:100', 'unique:plans,slug', 'regex:/^[a-z0-9\-]+$/'],
            'description'      => ['nullable', 'string'],
            'price'            => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:monthly,yearly'],
            'status'           => ['sometimes', 'string', 'max:30'],
        ];
    }
}
