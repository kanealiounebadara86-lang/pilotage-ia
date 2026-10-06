<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.manage');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'campaign_id' => ['nullable', 'exists:marketing_campaigns,id'],
            'sale_date' => ['nullable', 'date'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:especes,mobile_money,virement,cheque,carte'],
            'pay_now' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
