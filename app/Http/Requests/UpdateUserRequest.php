<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only a Super Administrator may assign the Super Administrator role or
     * modify an account that already holds it (global, cross-tenant concern).
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

        $target = $this->route('user');

        if ($target && $target->hasRole('Super Administrator')) {
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
        $userId = $this->route('user')?->id;

        return [
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$userId],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$userId],
            'phone' => ['nullable', 'string', 'max:20'],
            'nida_number' => ['required', 'string', 'max:30', 'unique:users,nida_number,'.$userId],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
            'email.required' => 'Email address is required.',
            'nida_number.required' => 'NIDA number is required.',
            'status.required' => 'Status is required.',
            'roles.required' => 'Please select at least one role.',
        ];
    }
}
