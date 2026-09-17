<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for the reversal.',
            'reason.min' => 'Reversal reason must be at least 10 characters.',
            'reason.max' => 'Reversal reason cannot exceed 500 characters.',
        ];
    }
}
