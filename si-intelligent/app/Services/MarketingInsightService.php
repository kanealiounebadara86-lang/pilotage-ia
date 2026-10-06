<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingInsightService
{
    private const DECLINE_THRESHOLD = 0.30; // baisse de 30%+ des ventes vs période précédente = signal

    /**
     * IA Marketing : identifie les produits dont les ventes ralentissent
     * malgré une bonne marge (candidats à une campagne pour relancer la
     * demande), et les produits en forte croissance (candidats à capitaliser
     * dessus). Ne crée jamais la campagne elle-même — génère une proposition
     * que l'utilisateur valide explicitement (section 22 : Proposition →
     * Validation → Action réelle).
     */
    public function suggestCampaigns(int $companyId): array
    {
        $now = Carbon::now();
        $recentStart = $now->copy()->subDays(30)->toDateString();
        $previousStart = $now->copy()->subDays(60)->toDateString();
        $previousEnd = $now->copy()->subDays(31)->toDateString();

        $recent = $this->salesByProduct($companyId, $recentStart, $now->toDateString());
        $previous = $this->salesByProduct($companyId, $previousStart, $previousEnd);

        $suggestions = [];

        foreach ($recent as $productId => $data) {
            $previousQty = $previous[$productId]['quantity'] ?? 0;
            if ($previousQty === 0) {
                continue; // pas d'historique comparable, on ne spécule pas
            }

            $change = ($data['quantity'] - $previousQty) / $previousQty;

            if ($change <= -self::DECLINE_THRESHOLD) {
                $suggestedBudget = round($data['revenue'] * 0.10, -2); // 10% du CA récent du produit, arrondi
                $suggestions[] = [
                    'type' => 'relance',
                    'product_id' => $productId,
                    'product_name' => $data['name'],
                    'rationale' => "Ventes en baisse de ".round(abs($change) * 100)."% sur 30 jours (".$data['quantity']." vs {$previousQty} unités sur la période précédente).",
                    'suggested_campaign_name' => "Relance — {$data['name']}",
                    'suggested_budget' => $suggestedBudget,
                    'suggested_duration_days' => 21,
                ];
            } elseif ($change >= 0.30 && $data['quantity'] >= 5) {
                $suggestedBudget = round($data['revenue'] * 0.08, -2);
                $suggestions[] = [
                    'type' => 'capitaliser',
                    'product_id' => $productId,
                    'product_name' => $data['name'],
                    'rationale' => "Forte croissance : +".round($change * 100)."% sur 30 jours (".$data['quantity']." vs {$previousQty} unités). Capitaliser pendant que la demande est là.",
                    'suggested_campaign_name' => "Pousser — {$data['name']}",
                    'suggested_budget' => $suggestedBudget,
                    'suggested_duration_days' => 14,
                ];
            }
        }

        return [
            'period_analyzed' => ['from' => $recentStart, 'to' => $now->toDateString()],
            'suggestions' => $suggestions,
            'status' => empty($suggestions) ? 'rien_a_signaler' : 'suggestions_disponibles',
        ];
    }

    private function salesByProduct(int $companyId, string $from, string $to): array
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'confirmed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('products.id, products.name, SUM(sale_items.quantity) as quantity, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->get()
            ->keyBy('id')
            ->map(fn ($row) => ['name' => $row->name, 'quantity' => (int) $row->quantity, 'revenue' => (float) $row->revenue])
            ->toArray();
    }
}
