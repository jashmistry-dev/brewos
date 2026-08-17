<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'branch_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($cafeId) {
                    $validBranch = Branch::where('id', $value)
                        ->where('cafe_id', $cafeId)
                        ->exists();

                    if (! $validBranch) {
                        $fail('The selected branch is invalid or does not belong to this cafe.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:available,occupied,reserved,inactive'],
        ];
    }
}
