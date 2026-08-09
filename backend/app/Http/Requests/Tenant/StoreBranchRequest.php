<?php

namespace App\Http\Requests\Tenant;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:100',
                Rule::unique('branches', 'slug')->where('cafe_id', $cafeId),
            ],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
