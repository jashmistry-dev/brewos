<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feature_key' => ['required', 'string', 'max:100'],
            'value'       => ['required', 'string', 'max:255'],
        ];
    }
}
