<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'address'       => ['nullable', 'string'],
            'gst_number'    => ['nullable', 'string', 'max:50'],
            'logo'          => ['nullable', 'string', 'max:500'],
            'footer_text'   => ['nullable', 'string'],
        ];
    }
}
