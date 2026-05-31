<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableUpdateRequest extends FormRequest
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
        $id = $this->route('id');
        return [
            'table_no' => ['required', 'string', 'max:100', Rule::unique('tables', 'table_no')->ignore($id)->where(fn ($q) => $q->where('business_id', $businessId))],
            'qr_code' => ['required', 'string', 'max:160', Rule::unique('tables', 'qr_code')->ignore($id)->where(fn ($q) => $q->where('business_id', $businessId))],
            'is_active' => 'nullable|boolean',
        ];
    }
}
