<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('stock.manage');
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'primary_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reference' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'reference')->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_min' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock_max' => ['nullable', 'integer', 'gte:stock_min'],
            'safety_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_perishable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
