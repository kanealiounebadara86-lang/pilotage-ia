<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly FinanceService $financeService,
        private readonly AccountingService $accountingService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * Crée une vente avec ses lignes, calcule automatiquement le chiffre d'affaires,
     * le coût, la marge (montant et %), et impacte le stock de chaque produit vendu.
     * Cf. cahier des charges module 12 : "chaque vente doit avoir un impact sur le stock".
     *
     * @param  array{customer_id?:int, payment_method?:string, items: array<int, array{product_id:int, quantity:int, unit_price?:float, discount?:float}>}  $data
     */
    public function createSale(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            $companyId = $user->company_id;

            $totalAmount = 0;
            $totalCost = 0;
            $totalDiscount = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = Product::where('company_id', $companyId)->findOrFail($item['product_id']);

                $unitPrice = $item['unit_price'] ?? $product->sale_price;
                $discount = $item['discount'] ?? 0;
                $quantity = $item['quantity'];
                $lineTotal = ($unitPrice * $quantity) - $discount;

                $totalAmount += $lineTotal;
                $totalCost += $product->cost * $quantity;
                $totalDiscount += $discount;

                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $product->cost,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $marginAmount = $totalAmount - $totalCost;
            $marginPercent = $totalAmount > 0 ? round(($marginAmount / $totalAmount) * 100, 2) : 0;

            $sale = Sale::create([
                'company_id' => $companyId,
                'customer_id' => $data['customer_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'user_id' => $user->id,
                'reference' => 'VTE-'.strtoupper(Str::random(8)),
                'sale_date' => $data['sale_date'] ?? now()->toDateString(),
                'discount_total' => $totalDiscount,
                'tax_total' => $data['tax_total'] ?? 0,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'margin_amount' => $marginAmount,
                'margin_percent' => $marginPercent,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => 'confirmed',
            ]);

            foreach ($lines as $line) {
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'],
                    'line_total' => $line['line_total'],
                ]);

                // Impact stock : une vente confirmée sort le produit du stock.
                $this->stockService->recordMovement(
                    product: $line['product'],
                    type: 'sortie',
                    quantitySigned: -$line['quantity'],
                    user: $user,
                    reason: "Vente {$sale->reference}",
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                );
            }

            $this->accountingService->recordSale($sale);
            $this->loyaltyService->awardPointsForSale($sale);

            // Encaissement immédiat (caisse) : sinon la vente reste une créance
            // et n'entre PAS dans le chiffre d'affaires tant qu'elle n'est pas payée.
            if (! empty($data['pay_now']) && $totalAmount > 0) {
                $this->paymentService->recordSalePayment(
                    $sale, (float) $totalAmount, PaymentService::normalizeMethod($data['payment_method'] ?? null), null, $user
                );
            }

            return $sale->fresh(['items.product']);
        });
    }

    /**
     * Annule une vente confirmée : remet le stock, contre-passe l'écriture
     * comptable, rembourse ce qui avait été encaissé (le chiffre d'affaires
     * encaissé redescend) et retire les points de fidélité. Ne supprime jamais
     * la vente (traçabilité comptable).
     */
    public function cancelSale(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user) {
            abort_if($sale->status === 'cancelled', 409, 'Cette vente est déjà annulée.');
            abort_if((float) $sale->returned_amount > 0.01, 409, 'Cette vente a déjà des retours : traitez le reste par un retour client.');

            foreach ($sale->items as $item) {
                $this->stockService->recordMovement(
                    product: $item->product,
                    type: 'correction',
                    quantitySigned: $item->quantity,
                    user: $user,
                    reason: "Annulation vente {$sale->reference}",
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                );
            }

            $paid = $sale->amountPaid();
            $method = $sale->payments()->where('amount', '>', 0)->latest('id')->value('method') ?? 'especes';

            $sale->update(['status' => 'cancelled']);
            $this->accountingService->recordSaleCancellation($sale);

            if ($paid > 0.01) {
                $this->paymentService->refundSalePayment($sale, $paid, $method, $user, "Annulation vente {$sale->reference}");
            }

            $this->loyaltyService->revokePointsForAmount($sale, (float) $sale->total_amount);
            $sale->update(['payment_status' => 'non_payee']);

            return $sale->fresh(['items.product']);
        });
    }
}
