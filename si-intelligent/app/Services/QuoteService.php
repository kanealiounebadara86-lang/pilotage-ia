<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuoteService
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    public function createQuote(array $data, User $user): Quote
    {
        return DB::transaction(function () use ($data, $user) {
            $totalAmount = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = Product::where('company_id', $user->company_id)->findOrFail($item['product_id']);
                $unitPrice = $item['unit_price'] ?? $product->sale_price;
                $lineTotal = $unitPrice * $item['quantity'];
                $totalAmount += $lineTotal;

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $quote = Quote::create([
                'company_id' => $user->company_id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $user->id,
                'reference' => 'DEV-'.strtoupper(Str::random(8)),
                'quote_date' => now()->toDateString(),
                'valid_until' => $data['valid_until'] ?? now()->addDays(30)->toDateString(),
                'total_amount' => $totalAmount,
                'status' => 'brouillon',
            ]);

            foreach ($lines as $line) {
                $quote->items()->create($line);
            }

            return $quote->load('items.product', 'customer');
        });
    }

    /**
     * Convertit un devis accepté en vente réelle : réutilise SalesService
     * (donc impact stock + finance + comptabilité automatiques, comme
     * n'importe quelle autre vente).
     */
    public function convertToSale(Quote $quote, User $user): Quote
    {
        abort_if($quote->status === 'converti', 409, 'Ce devis a déjà été converti.');

        $sale = $this->salesService->createSale([
            'customer_id' => $quote->customer_id,
            'items' => $quote->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->toArray(),
        ], $user);

        $quote->update(['status' => 'converti', 'converted_sale_id' => $sale->id]);

        return $quote->fresh(['items.product', 'convertedSale']);
    }
}
