<?php

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'category_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($cafeId) {
                    $validCategory = Category::where('id', $value)
                        ->where('cafe_id', $cafeId)
                        ->exists();

                    if (! $validCategory) {
                        $fail('The selected category is invalid or does not belong to this cafe.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'status' => ['sometimes', 'string', 'in:active,available,unavailable,inactive'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
