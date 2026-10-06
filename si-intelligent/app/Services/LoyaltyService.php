<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;

class LoyaltyService
{
    /**
     * Règle simple : 1 point de fidélité par tranche de 1000 (unité monétaire)
     * dépensée. Paramètre volontairement en dur ici pour rester simple ;
     * à extraire vers les paramètres entreprise si le besoin de personnalisation
     * par client apparaît.
     */
    private const XOF_PER_POINT = 1000;

    /** Retire les points correspondant à un montant remboursé / annulé. */
    public function revokePointsForAmount(Sale $sale, float $amount): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $points = intdiv((int) $amount, self::XOF_PER_POINT);
        $customer = Customer::find($sale->customer_id);

        if ($customer && $points > 0) {
            $customer->update(['loyalty_points' => max(0, (int) $customer->loyalty_points - $points)]);
        }
    }

    public function awardPointsForSale(Sale $sale): void
    {
        if (! $sale->customer_id) {
            return; // pas de fidélité pour un client anonyme
        }

        $points = intdiv((int) $sale->total_amount, self::XOF_PER_POINT);

        if ($points > 0) {
            Customer::where('id', $sale->customer_id)->increment('loyalty_points', $points);
        }
    }
}
