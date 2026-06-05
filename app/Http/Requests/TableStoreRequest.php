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
        return [
            'table_no' => ['required', 'string', 'max:100', Rule::unique('tables', 'table_no')],
            'qr_code' => ['nullable', 'string', 'max:160', 'regex:/^tbl_[A-Za-z0-9]{40}$/', Rule::unique('tables', 'qr_code')],
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'table_no.unique' => 'Table number already exists. Please use a different table number.',
            'qr_code.unique' => 'This table QR code already exists. Please generate a new QR code.',
            'qr_code.regex' => 'The table QR code is invalid. Please generate a new QR code.',
        ];
    }
}
