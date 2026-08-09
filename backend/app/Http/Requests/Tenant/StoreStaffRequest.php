<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use App\Models\Role;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($cafeId) {
                    $validRole = Role::where('id', $value)
                        ->where(function ($query) use ($cafeId) {
                            $query->where('cafe_id', $cafeId)
                                  ->orWhere(function ($sub) {
                                      $sub->whereNull('cafe_id')
                                          ->where('scope', 'platform');
                                  });
                        })->exists();

                    if (! $validRole) {
                        $fail('The selected role is invalid or does not belong to this cafe.');
                    }
                },
            ],
            'branch_id' => [
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
            'status' => ['sometimes', 'string', 'in:active,suspended,inactive'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $cafeId = app(TenantContext::class)->getCafeId();
            if ($cafeId && app(\App\Services\PlanLimitService::class)->hasReachedStaffLimit($cafeId)) {
                $validator->errors()->add('staff', 'Staff member limit reached for your active subscription plan.');
            }
        });
    }
}
