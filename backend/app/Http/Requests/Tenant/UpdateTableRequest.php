<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:available,occupied,reserved,inactive'],
        ];
    }
}
