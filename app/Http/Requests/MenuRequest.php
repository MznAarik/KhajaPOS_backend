<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.price' => 'required_with:items|numeric',
            'items.*.food_type' => 'required_with:items|string|in:veg,non-veg,egg,vegan',
            'items.*.image_url' => 'nullable|string',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
        ];
    }
}
