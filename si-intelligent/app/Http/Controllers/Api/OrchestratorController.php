<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Services\FinanceInsightService;
use App\Services\HrInsightService;
use App\Services\MarketingInsightService;
use Illuminate\Http\Request;

class OrchestratorController extends Controller
{
    public function __construct(
        private readonly ForecastController $forecastController,
        private readonly ReplenishmentController $replenishmentController,
        private readonly HrInsightService $hrInsightService,
        private readonly FinanceInsightService $financeInsightService,
        private readonly MarketingInsightService $marketingInsightService,
    ) {
    }

    /**
     * L'orchestrateur : un seul déclenchement qui lance automatiquement
     * chaque IA spécialisée (prévision, réapprovisionnement, RH, finance,
     * marketing) sur le périmètre demandé, puis compile une synthèse.
     *
     * Chaque IA reste utilisable indépendamment via ses propres routes —
     * l'orchestrateur ne fait qu'automatiser leur enchaînement. Aucune IA,
     * ici ou ailleurs, n'exécute d'action réelle (commande, encaissement,
     * envoi de message) sans validation humaine explicite sur la ressource
     * concernée (cf. ReplenishmentController::decide).
     */
    public function run(Request $request)
    {
        abort_unless($request->user()->hasPermission('ai.view_recommendations'), 403);

        $companyId = $request->user()->company_id;
        $runProducts = $request->boolean('include_products', true);
        $runEmployees = $request->boolean('include_employees', true);
        $runFinance = $request->boolean('include_finance', true);
        $runMarketing = $request->boolean('include_marketing', true);

        $productResults = ['forecast' => [], 'replenishment' => []];
        $employeeResults = [];
        $financeResult = null;
        $marketingResult = null;

        if ($runProducts) {
            $products = $request->input('product_scope') === 'all'
                ? Product::where('company_id', $companyId)->where('is_active', true)->get()
                : Product::where('company_id', $companyId)->whereIn('id', $request->input('product_ids', []))->get();

            foreach ($products as $product) {
                $productResults['forecast'][] = $this->forecastController->runForProduct($product, 14);
                $productResults['replenishment'][] = $this->replenishmentController->runForProduct($product, $request->user());
            }
        }

        if ($runEmployees) {
            $employees = $request->input('employee_scope') === 'all'
                ? Employee::where('company_id', $companyId)->where('status', 'actif')->get()
                : Employee::where('company_id', $companyId)->whereIn('id', $request->input('employee_ids', []))->get();

            $employeeResults = $employees->map(fn ($e) => $this->hrInsightService->analyzeEmployee($e))->values();
        }

        if ($runFinance) {
            $financeResult = $this->financeInsightService->analyze($companyId);
        }

        if ($runMarketing) {
            $marketingResult = $this->marketingInsightService->suggestCampaigns($companyId);
        }

        return response()->json([
            'summary' => $this->buildSummary($productResults, $employeeResults, $financeResult, $marketingResult),
            'products' => $productResults,
            'employees' => $employeeResults,
            'finance' => $financeResult,
            'marketing' => $marketingResult,
        ]);
    }

    private function buildSummary(array $productResults, $employeeResults, ?array $financeResult, ?array $marketingResult): array
    {
        $replenishment = collect($productResults['replenishment']);
        $toOrder = $replenishment->where('status', 'ok')->where('decision_type', 'commander');
        $critical = $toOrder->where('priority', 'critique');

        return [
            'products_analyzed' => count($productResults['forecast']),
            'products_to_order' => $toOrder->count(),
            'critical_stock_alerts' => $critical->count(),
            'employees_analyzed' => count($employeeResults),
            'employees_flagged' => collect($employeeResults)->where('status', 'a_surveiller')->count(),
            'finance_signals' => $financeResult ? count($financeResult['signals']) : 0,
            'marketing_suggestions' => $marketingResult ? count($marketingResult['suggestions']) : 0,
        ];
    }
}
