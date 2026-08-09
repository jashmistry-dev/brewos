<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class CreatePublicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer'],
        ];
    }
}
