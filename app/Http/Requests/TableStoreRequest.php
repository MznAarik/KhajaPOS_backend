<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableStoreRequest extends FormRequest
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
        $businessId = auth()->user()->business->id ?? 0;
        return [
            'table_no' => ['required', 'string', 'max:100', Rule::unique('tables', 'table_no')->where(fn ($q) => $q->where('business_id', $businessId))],
            'qr_code' => ['nullable', 'string', 'max:160', Rule::unique('tables', 'qr_code')->where(fn ($q) => $q->where('business_id', $businessId))],
            'is_active' => 'nullable|boolean',
        ];
    }
}
