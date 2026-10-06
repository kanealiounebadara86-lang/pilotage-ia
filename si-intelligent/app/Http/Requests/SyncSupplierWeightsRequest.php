<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncSupplierWeightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('purchases.manage');
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:1'],
            'lead_time' => ['required', 'numeric', 'min:0', 'max:1'],
            'quality' => ['required', 'numeric', 'min:0', 'max:1'],
            'reliability' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
