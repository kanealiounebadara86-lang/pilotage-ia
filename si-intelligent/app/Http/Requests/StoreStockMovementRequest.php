<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('stock.manage');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            // Saisie manuelle réservée aux ajustements ; entrée/sortie automatiques
            // passent par les ventes et les réceptions d'achats.
            'type' => ['required', 'in:correction,perte,inventaire,transfert'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
