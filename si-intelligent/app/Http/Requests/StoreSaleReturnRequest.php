<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('sales.manage');
    }

    public function rules(): array
    {
        return [
            'sale_item_id' => ['required', 'exists:sale_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'in:defectueux,erreur_commande,insatisfaction,autre'],
            'restock' => ['boolean'],
            'refund_method' => ['nullable', 'in:especes,mobile_money,virement,cheque,carte'],
        ];
    }
}
