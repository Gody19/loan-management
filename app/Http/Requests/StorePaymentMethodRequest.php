<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMethodId = $this->route('payment_method')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:payment_methods,code,' . ($paymentMethodId ?? 'null') . ',id'],
            'type' => ['required', 'string', 'in:cash,bank,mobile_money,card,transfer,other'],
            'organization_id' => ['required', 'exists:organizations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Payment method name is required.',
            'code.required' => 'Payment method code is required.',
            'code.unique' => 'This payment method code already exists.',
            'type.required' => 'Payment method type is required.',
            'type.in' => 'Invalid payment method type.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
        ];
    }
}
