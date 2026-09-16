<?php

namespace App\Http\Requests;

use App\Enums\CollateralType;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collateral_type' => ['required', 'string', 'in:' . implode(',', CollateralType::values())],
            'description' => ['required', 'string', 'max:1000'],
            'estimated_value' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'ownership_details' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'collateral_type.required' => 'Collateral type is required.',
            'description.required' => 'Description is required.',
            'estimated_value.required' => 'Estimated value is required.',
            'estimated_value.min' => 'Estimated value must be at least 0.01.',
        ];
    }
}
