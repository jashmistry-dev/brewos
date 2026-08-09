<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use App\Models\Role;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafeId = app(TenantContext::class)->getCafeId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'role_id' => [
                'sometimes',
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
}
