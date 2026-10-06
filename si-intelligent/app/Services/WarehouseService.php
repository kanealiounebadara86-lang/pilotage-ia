<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Transfère du stock d'un entrepôt à un autre : une sortie sur l'entrepôt
     * source, une entrée sur l'entrepôt destination — jamais une simple
     * modification directe des quantités.
     */
    public function transfer(Product $product, Warehouse $from, Warehouse $to, int $quantity, User $user): void
    {
        abort_if($from->id === $to->id, 422, 'L\'entrepôt source et destination doivent être différents.');

        DB::transaction(function () use ($product, $from, $to, $quantity, $user) {
            $this->stockService->recordMovement(
                product: $product,
                type: 'transfert',
                quantitySigned: -$quantity,
                user: $user,
                reason: "Transfert vers {$to->name}",
                referenceType: Warehouse::class,
                referenceId: $to->id,
            );

            $this->stockService->recordMovement(
                product: $product,
                type: 'transfert',
                quantitySigned: $quantity,
                user: $user,
                reason: "Transfert depuis {$from->name}",
                referenceType: Warehouse::class,
                referenceId: $from->id,
            );

            \App\Models\StockTransfer::create([
                'company_id' => $product->company_id,
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => $quantity,
                'user_id' => $user->id,
            ]);
        });
    }
}
