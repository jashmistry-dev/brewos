<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'branch_id'  => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($cafeId) {
                    if (! $value) {
                        return;
                    }

                    $validBranch = Branch::where('id', $value)
                        ->where('cafe_id', $cafeId)
                        ->exists();

                    if (! $validBranch) {
                        $fail('The selected branch is invalid or does not belong to this cafe.');
                    }
                },
            ],
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
