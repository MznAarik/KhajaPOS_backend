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
        $id = $this->route('id');
        return [
            'table_no' => ['required', 'string', 'max:100', Rule::unique('tables', 'table_no')->ignore($id)],
            'qr_code' => ['required', 'string', 'max:160', Rule::unique('tables', 'qr_code')->ignore($id)],
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'table_no.unique' => 'Table number already exists. Please use a different table number.',
            'qr_code.unique' => 'This table QR code already exists. Please generate a new QR code.',
        ];
    }
}
