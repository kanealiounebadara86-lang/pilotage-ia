<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('finance.manage') || $this->user()->hasPermission('sales.manage') || $this->user()->hasPermission('purchases.manage');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:especes,mobile_money,virement,cheque,carte'],
            'payment_date' => ['nullable', 'date'],
        ];
    }
}
