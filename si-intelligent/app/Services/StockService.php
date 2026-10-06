<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;

class StockService
{
    /**
     * Enregistre un mouvement de stock, met à jour le stock disponible du produit,
     * et déclenche les alertes intelligentes (rupture / surstock) si besoin.
     * C'est le point de passage UNIQUE pour toute variation de stock dans le système
     * (module 13), afin que stock_levels reste toujours cohérent avec stock_movements.
     */
    public function recordMovement(
        Product $product,
        string $type,
        int $quantitySigned,
        ?User $user = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        $movement = StockMovement::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'type' => $type,
            'quantity' => $quantitySigned,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        $stockLevel = $product->stockLevel ?? StockLevel::create([
            'product_id' => $product->id,
            'quantity_available' => 0,
        ]);

        $stockLevel->quantity_available += $quantitySigned;
        $stockLevel->last_movement_at = now();
        $stockLevel->save();

        $this->checkAndRaiseAlerts($product, $stockLevel);

        return $movement;
    }

    /**
     * Vérifie les seuils du produit et crée une alerte si nécessaire.
     * N'ouvre pas de doublon : si une alerte du même type est déjà active
     * (non résolue) pour ce produit, on ne la recrée pas.
     */
    private function checkAndRaiseAlerts(Product $product, StockLevel $stockLevel): void
    {
        if ($stockLevel->quantity_available <= 0) {
            $this->raiseAlertOnce($product, 'rupture', 'critique',
                "Rupture de stock sur « {$product->name} » (référence {$product->reference}).");
        } elseif ($stockLevel->quantity_available <= $product->stock_min) {
            $this->raiseAlertOnce($product, 'risque_rupture', 'elevee',
                "Risque de rupture sur « {$product->name} » : stock à {$stockLevel->quantity_available}, seuil minimum {$product->stock_min}.");
        } else {
            $this->resolveAlertsOfType($product, ['rupture', 'risque_rupture']);
        }

        if ($product->stock_max !== null && $stockLevel->quantity_available >= $product->stock_max) {
            $this->raiseAlertOnce($product, 'surstock', 'moyenne',
                "Surstock sur « {$product->name} » : stock à {$stockLevel->quantity_available}, seuil maximum {$product->stock_max}.");
        } else {
            $this->resolveAlertsOfType($product, ['surstock']);
        }
    }

    private function raiseAlertOnce(Product $product, string $type, string $severity, string $message): void
    {
        $exists = Alert::where('reference_type', Product::class)
            ->where('reference_id', $product->id)
            ->where('type', $type)
            ->where('is_resolved', false)
            ->exists();

        if (! $exists) {
            Alert::create([
                'company_id' => $product->company_id,
                'type' => $type,
                'severity' => $severity,
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'message' => $message,
            ]);
        }
    }

    private function resolveAlertsOfType(Product $product, array $types): void
    {
        Alert::where('reference_type', Product::class)
            ->where('reference_id', $product->id)
            ->whereIn('type', $types)
            ->where('is_resolved', false)
            ->update(['is_resolved' => true, 'resolved_at' => now()]);
    }
}
