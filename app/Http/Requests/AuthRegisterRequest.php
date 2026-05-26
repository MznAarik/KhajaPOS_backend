<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|min:5',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'nullable|in:user,admin',
            'business.name' => 'nullable|string|max:255',
            'business.business_type' => 'nullable|string|max:100',
            'business.phone' => 'nullable|string|max:50',
            'business.email' => 'nullable|email|max:255',
            'business.address' => 'nullable|string|max:500',
        ];
    }
}
