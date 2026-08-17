<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCafeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ownerEmail = $this->input('owner_email') ?? $this->input('email');
        $cafeEmail  = $this->input('email') ?? $ownerEmail;

        $this->merge([
            'owner_email' => $ownerEmail ? trim(strtolower($ownerEmail)) : null,
            'email'       => $cafeEmail ? trim(strtolower($cafeEmail)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'slug'                  => ['required', 'string', 'alpha_dash', 'max:100', 'unique:cafes,slug'],
            'email'                 => ['nullable', 'string', 'email', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'owner_name'            => ['required', 'string', 'max:255'],
            'owner_email'           => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
