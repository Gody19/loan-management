<?php

namespace App\Http\Requests;

use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreShareProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->organization_id) {
            return true;
        }

        return OrganizationContext::userBelongsToOrganization((int) $this->organization_id);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'share_price' => ['required', 'numeric', 'min:0.01'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'minimum_shares' => ['required', 'integer', 'min:1'],
            'maximum_shares' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Share product name is required.',
            'code.required' => 'Share product code is required.',
            'share_price.required' => 'Share price is required.',
            'share_price.numeric' => 'Share price must be a valid number.',
            'share_price.min' => 'Share price must be at least 0.01.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'minimum_shares.required' => 'Minimum shares is required.',
            'minimum_shares.integer' => 'Minimum shares must be a whole number.',
            'minimum_shares.min' => 'Minimum shares must be at least 1.',
        ];
    }
}
