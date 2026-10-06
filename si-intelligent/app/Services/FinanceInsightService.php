<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;

class FinanceInsightService
{
    private const CASH_RISK_MARGIN = 0.10; // alerte si les dettes dépassent 90% de (trésorerie + créances)
    private const EXPENSE_ANOMALY_MULTIPLIER = 2.0; // dépense 2x supérieure à sa moyenne habituelle

    public function __construct(private readonly FinanceService $financeService)
    {
    }

    /**
     * IA Finance : détecte les signaux de risque à partir des données réelles
     * — jamais de prédiction inventée, uniquement des règles vérifiables sur
     * les comptes et transactions existants (même esprit que l'IA RH).
     */
    public function analyze(int $companyId): array
    {
        $kpis = $this->financeService->getExtendedKpis($companyId);
        $signals = [];

        // Risque de trésorerie : les dettes fournisseurs menacent-elles la capacité de paiement ?
        $available = $kpis['cash_position'] + $kpis['receivables'];
        if ($available > 0 && $kpis['payables'] >= $available * (1 - self::CASH_RISK_MARGIN)) {
            $signals[] = [
                'type' => 'risque_tresorerie',
                'severity' => 'elevee',
                'label' => "Risque de trésorerie : les dettes fournisseurs ({$kpis['payables']} XOF) représentent une part critique de la trésorerie + créances disponibles ({$available} XOF).",
            ];
        }

        // Anomalies de dépenses : une catégorie de dépense ce mois-ci très supérieure à sa moyenne historique
        $anomalies = $this->detectExpenseAnomalies($companyId);
        foreach ($anomalies as $anomaly) {
            $signals[] = [
                'type' => 'anomalie_depense',
                'severity' => 'moyenne',
                'label' => "Dépense « {$anomaly['category']} » inhabituelle ce mois-ci : {$anomaly['current']} XOF contre {$anomaly['average']} XOF en moyenne.",
            ];
        }

        return [
            'kpis' => $kpis,
            'signals' => $signals,
            'status' => empty($signals) ? 'rien_a_signaler' : 'a_surveiller',
        ];
    }

    private function detectExpenseAnomalies(int $companyId): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $sixMonthsAgo = Carbon::now()->subMonths(6)->toDateString();

        $currentByCategory = FinancialTransaction::where('company_id', $companyId)
            ->where('type', 'depense')
            ->where('transaction_date', '>=', $currentMonthStart)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $historicalByCategory = FinancialTransaction::where('company_id', $companyId)
            ->where('type', 'depense')
            ->whereBetween('transaction_date', [$sixMonthsAgo, $currentMonthStart])
            ->selectRaw("category, SUM(amount) / 6 as monthly_avg")
            ->groupBy('category')
            ->pluck('monthly_avg', 'category');

        $anomalies = [];
        foreach ($currentByCategory as $category => $current) {
            $average = (float) ($historicalByCategory[$category] ?? 0);
            if ($average > 0 && $current >= $average * self::EXPENSE_ANOMALY_MULTIPLIER) {
                $anomalies[] = ['category' => $category, 'current' => round($current, 2), 'average' => round($average, 2)];
            }
        }

        return $anomalies;
    }
}
