<?php

namespace App\Services;

use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly AccountingService $accountingService,
        private readonly PaymentService $paymentService,
        private readonly LoyaltyService $loyaltyService,
    ) {
    }

    /**
     * Retour d'une ligne de vente. Tout ce qui en dépend est synchronisé :
     *  - stock remis (si la marchandise est revendable) ;
     *  - vente diminuée (returned_amount) → chiffre d'affaires net en baisse ;
     *  - écriture comptable (Ventes / Clients, + Stock / Coût des marchandises) ;
     *  - si le client avait déjà payé plus que le nouveau net : remboursement
     *    d'argent (paiement négatif, trésorerie en baisse) ; sinon la créance
     *    client est simplement réduite (pas de sortie d'argent) ;
     *  - points de fidélité retirés.
     */
    public function processReturn(
        SaleItem $saleItem,
        int $quantity,
        string $reason,
        bool $restock,
        User $user,
        string $refundMethod = 'especes',
    ): SaleReturn {
        return DB::transaction(function () use ($saleItem, $quantity, $reason, $restock, $user, $refundMethod) {
            $sale = $saleItem->sale;
            abort_if($sale->status === 'cancelled', 422, 'Cette vente est annulée : aucun retour possible.');

            $alreadyReturned = (int) SaleReturn::where('sale_item_id', $saleItem->id)->sum('quantity');
            abort_if(
                $quantity > $saleItem->quantity - $alreadyReturned,
                422,
                'Quantité retournée supérieure à la quantité restante ('.($saleItem->quantity - $alreadyReturned).' encore retournable).'
            );

            // Valeur réellement payée par le client pour ces unités (remise comprise).
            $refundValue = round(((float) $saleItem->line_total / $saleItem->quantity) * $quantity, 2);
            $costValue = round((float) $saleItem->unit_cost * $quantity, 2);

            $return = SaleReturn::create([
                'company_id' => $sale->company_id,
                'sale_id' => $saleItem->sale_id,
                'sale_item_id' => $saleItem->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'restocked' => $restock,
                'refund_amount' => $refundValue,
            ]);

            if ($restock) {
                $this->stockService->recordMovement(
                    product: $saleItem->product,
                    type: 'correction',
                    quantitySigned: $quantity,
                    user: $user,
                    reason: "Retour vente {$sale->reference}",
                    referenceType: SaleReturn::class,
                    referenceId: $return->id,
                );
            }

            $sale->increment('returned_amount', $refundValue);
            $this->accountingService->recordSaleReturn($sale, $refundValue, $costValue, $restock);

            // Argent déjà encaissé au-delà du nouveau net : on le rend au client.
            $overpaid = round($sale->amountPaid() - $sale->netTotal(), 2);
            $cashRefunded = 0.0;
            if ($overpaid > 0.01) {
                $cashRefunded = min($overpaid, $refundValue);
                $this->paymentService->refundSalePayment(
                    $sale, $cashRefunded, PaymentService::normalizeMethod($refundMethod), $user,
                    "Remboursement retour {$sale->reference}"
                );
            } else {
                $sale->update(['payment_status' => $this->statusFor($sale)]);
            }

            $this->loyaltyService->revokePointsForAmount($sale, $refundValue);

            $return->setAttribute('cash_refunded', $cashRefunded);

            return $return->load('saleItem.product', 'sale');
        });
    }

    private function statusFor($sale): string
    {
        $paid = $sale->amountPaid();
        if ($paid <= 0.01) {
            return 'non_payee';
        }

        return $paid >= $sale->netTotal() - 0.01 ? 'payee' : 'partielle';
    }
}
