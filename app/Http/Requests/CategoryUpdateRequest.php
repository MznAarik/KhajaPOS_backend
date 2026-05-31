<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessId = auth()->user()->business->id ?? 0;
        $id = $this->route('id');
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($id)->where(fn ($q) => $q->where('business_id', $businessId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
