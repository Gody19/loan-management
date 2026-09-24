<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Assigning the Super Administrator role is a global (cross-tenant)
     * privilege escalation, so only a Super Administrator may request it.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor && $actor->hasRole('Super Administrator')) {
            return true;
        }

        if (in_array('Super Administrator', $this->input('roles', []), true)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'nida_number' => ['required', 'string', 'max:30', 'unique:users,nida_number'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,name'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'fullname.required' => 'Full name is required.',
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'This email address is already registered.',
            'nida_number.required' => 'NIDA number is required.',
            'nida_number.unique' => 'This NIDA number is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'roles.required' => 'Please select at least one role.',
        ];
    }
}
