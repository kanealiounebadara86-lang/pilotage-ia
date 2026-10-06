<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('ai.view_recommendations') || $this->user()->hasPermission('sales.view');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }
}
