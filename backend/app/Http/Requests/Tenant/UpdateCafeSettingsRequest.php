<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCafeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'status'      => ['sometimes', 'string', 'in:active,inactive'],
            'address'     => ['nullable', 'string', 'max:1000'],
            'city'        => ['nullable', 'string', 'max:100'],
            'state'       => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'max:100'],
            'tax_number'  => ['nullable', 'string', 'max:50'],
            'tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'timezone'    => ['nullable', 'string', 'max:50'],
            'currency'    => ['nullable', 'string', 'max:10'],
            'logo'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'logo_url'    => ['nullable', 'string', 'url', 'max:2048'],
        ];
    }
}
