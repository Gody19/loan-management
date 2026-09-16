<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            $rules['registration_number'] = ['required', 'string', 'max:50', 'unique:organizations,registration_number,'.$this->route('organization')->id];
        }

        // Admin creation rules
        if ($this->input('admin_type') === 'create') {
            $rules['admin_name'] = ['required', 'string', 'max:255'];
            $rules['admin_email'] = ['required', 'email', 'max:255', 'unique:users,email'];
            $rules['admin_phone'] = ['nullable', 'string', 'max:20'];
            $rules['admin_password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        // Admin assignment rules
        if ($this->input('admin_type') === 'assign') {
            $rules['admin_user_id'] = ['required', 'exists:users,id'];
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
            'admin_name.required' => 'Administrator name is required.',
            'admin_email.required' => 'Administrator email is required.',
            'admin_email.unique' => 'A user with this email already exists.',
            'admin_email.email' => 'Please provide a valid email address.',
            'admin_password.required' => 'Administrator password is required.',
            'admin_password.min' => 'Password must be at least 8 characters.',
            'admin_password.confirmed' => 'Password confirmation does not match.',
            'admin_user_id.required' => 'Please select a user to assign.',
            'admin_user_id.exists' => 'The selected user does not exist.',
        ];
    }
}
