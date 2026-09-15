<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:50', 'unique:organizations,registration_number'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['registration_number'] = ['required', 'string', 'max:50', 'unique:organizations,registration_number,' . $this->route('organization')->id];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
            'registration_number.required' => 'Registration number is required.',
            'registration_number.unique' => 'This registration number is already taken.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
