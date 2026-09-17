<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:50', 'unique:organizations,registration_number'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:organizations,email'],
            'address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['required', 'string', 'max:20'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your organization name.',
            'phone.required' => 'Please enter a contact phone number.',
            'email.required' => 'Please enter an email address for the organization.',
            'email.unique' => 'An organization with this email already exists.',
            'admin_name.required' => 'Please enter the administrator full name.',
            'admin_email.required' => 'Please enter the administrator email address.',
            'admin_email.unique' => 'A user with this email already exists.',
            'admin_phone.required' => 'Please enter the administrator phone number.',
            'admin_password.required' => 'Please create a password for the administrator account.',
            'admin_password.min' => 'The password must be at least 8 characters.',
            'admin_password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
