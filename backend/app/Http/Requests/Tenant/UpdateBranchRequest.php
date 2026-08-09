<?php

namespace App\Http\Requests\Tenant;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();
        $branchId = $this->route('branch_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:100',
                Rule::unique('branches', 'slug')->where('cafe_id', $cafeId)->ignore($branchId),
            ],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
