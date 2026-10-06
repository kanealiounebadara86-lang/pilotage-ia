<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly FinanceService $financeService,
        private readonly AccountingService $accountingService,
    ) {
    }

    /**
     * Crée une commande fournisseur (module 14). Le statut de départ est "commande"
     * (on part du principe qu'une commande créée manuellement est déjà validée ;
     * les recommandations générées par l'IA, elles, démarreront à "demande" en Phase 6).
     *
     * @param  array{supplier_id:int, expected_date?:string, items: array<int, array{product_id:int, quantity:int, unit_price?:float}>}  $data
     */
    public function createPurchaseOrder(array $data, User $user, bool $isAiGenerated = false): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user, $isAiGenerated) {
            $supplier = Supplier::where('company_id', $user->company_id)->findOrFail($data['supplier_id']);

            $totalAmount = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = Product::where('company_id', $user->company_id)->findOrFail($item['product_id']);
                $unitPrice = $item['unit_price'] ?? $product->purchase_price;
                $lineTotal = $unitPrice * $item['quantity'];
                $totalAmount += $lineTotal;

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $order = PurchaseOrder::create([
                'company_id' => $user->company_id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'reference' => 'CMD-'.strtoupper(Str::random(8)),
                'order_date' => now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'total_amount' => $totalAmount,
                'status' => $isAiGenerated ? 'demande' : 'commande',
                'is_ai_generated' => $isAiGenerated,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            return $order->load('items.product', 'supplier');
        });
    }

    /**
     * Enregistre une réception (partielle ou complète) d'une commande fournisseur :
     * met à jour les quantités reçues, fait entrer le stock, et recalcule le statut
     * global de la commande (module 14).
     *
     * @param  array<int, array{purchase_item_id:int, quantity_received:int}>  $receivedLines
     */
    public function receiveOrder(PurchaseOrder $order, array $receivedLines, User $user, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $receivedLines, $user, $notes) {
            abort_if($order->status === 'annulee', 409, 'Impossible de réceptionner une commande annulée.');

            foreach ($receivedLines as $line) {
                $item = $order->items()->findOrFail($line['purchase_item_id']);
                $quantity = $line['quantity_received'];

                abort_if(
                    $item->quantity_received + $quantity > $item->quantity_ordered,
                    422,
                    "Quantité reçue supérieure à la quantité commandée pour le produit #{$item->product_id}."
                );

                $item->increment('quantity_received', $quantity);

                $this->stockService->recordMovement(
                    product: $item->product,
                    type: 'entree',
                    quantitySigned: $quantity,
                    user: $user,
                    reason: "Réception commande {$order->reference}",
                    referenceType: PurchaseOrder::class,
                    referenceId: $order->id,
                );
            }

            $order->refresh();
            $isComplete = $order->items->every(fn ($item) => $item->quantity_received >= $item->quantity_ordered);

            $order->receipts()->create([
                'user_id' => $user->id,
                'receipt_date' => now()->toDateString(),
                'type' => $isComplete ? 'complete' : 'partielle',
                'notes' => $notes,
            ]);

            $order->update([
                'status' => $isComplete ? 'reception_complete' : 'reception_partielle',
                'received_date' => $isComplete ? now()->toDateString() : $order->received_date,
            ]);

            // Comptabilité : dette fournisseur sur la valeur effectivement reçue. La dépense
            // financière, elle, n'est constatée qu'au règlement du fournisseur (PaymentService).
            $receivedAmount = collect($receivedLines)->sum(function ($line) use ($order) {
                $item = $order->items->firstWhere('id', $line['purchase_item_id']);

                return $item ? $item->unit_price * $line['quantity_received'] : 0;
            });
            $this->accountingService->recordPurchaseReceipt($order, $receivedAmount);

            return $order->fresh(['items.product', 'receipts', 'supplier']);
        });
    }
}
