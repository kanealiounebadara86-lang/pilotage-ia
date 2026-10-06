<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'in:particulier,entreprise'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'segment' => ['nullable', 'string', 'max:100'],
        ];
    }
}
