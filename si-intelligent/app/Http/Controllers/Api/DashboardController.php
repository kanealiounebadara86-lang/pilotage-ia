<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
    }

    /**
     * Tableau de bord de direction (module 23) : agrège les KPI commerciaux,
     * stocks, achats et financiers en un seul appel. Tous les chiffres sont
     * calculés à la volée depuis la base — rien n'est codé en dur (section 38).
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $from = $request->query('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', Carbon::now()->toDateString());

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'commercial' => $this->commercialKpis($companyId, $from, $to),
            'stock' => $this->stockKpis($companyId),
            'purchases' => $this->purchaseKpis($companyId, $from, $to),
            'finance' => $this->financeService->getSummary($companyId, $from, $to),
            'hr' => $this->hrKpis($companyId, $from, $to),
            'alerts_summary' => $this->alertsSummary($companyId),
        ]);
    }

    private function commercialKpis(int $companyId, string $from, string $to): array
    {
        $sales = Sale::where('company_id', $companyId)
            ->where('status', 'confirmed')
            ->whereBetween('sale_date', [$from, $to]);

        $aggregate = (clone $sales)->selectRaw('
            COUNT(*) as sales_count,
            COALESCE(SUM(total_amount - returned_amount), 0) as invoiced
        ')->first();

        // Chiffre d'affaires = argent réellement ENCAISSÉ (net des remboursements).
        $collected = $this->financeService->getCollected($companyId, $from, $to);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'confirmed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('products.id, products.name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $topCustomers = $this->financeService->getProfitabilityByCustomer($companyId, $from, $to)->take(5);

        return [
            'sales_count' => (int) $aggregate->sales_count,
            'revenue' => $collected['revenue'],
            'margin' => $collected['margin'],
            'margin_percent' => $collected['revenue'] > 0 ? round(($collected['margin'] / $collected['revenue']) * 100, 2) : 0,
            'refunds' => $collected['refunds'],
            'invoiced' => round((float) $aggregate->invoiced, 2),
            'receivables' => $this->financeService->getOpenReceivables($companyId),
            'top_products' => $topProducts,
            'top_customers' => $topCustomers->values(),
        ];
    }

    private function stockKpis(int $companyId): array
    {
        $products = Product::where('company_id', $companyId)->with('stockLevel')->get();

        $totalUnits = $products->sum(fn ($p) => $p->stockLevel->quantity_available ?? 0);
        $totalValue = $products->sum(fn ($p) => ($p->stockLevel->quantity_available ?? 0) * $p->cost);

        $ruptures = $products->filter(fn ($p) => ($p->stockLevel->quantity_available ?? 0) <= 0)->count();
        $atRisk = $products->filter(fn ($p) => ($p->stockLevel->quantity_available ?? 0) > 0
            && ($p->stockLevel->quantity_available ?? 0) <= $p->stock_min)->count();
        $overstock = $products->filter(fn ($p) => $p->stock_max !== null
            && ($p->stockLevel->quantity_available ?? 0) >= $p->stock_max)->count();

        return [
            'total_products' => $products->count(),
            'total_units_in_stock' => (int) $totalUnits,
            'total_stock_value' => round((float) $totalValue, 2),
            'products_in_rupture' => $ruptures,
            'products_at_risk' => $atRisk,
            'products_in_overstock' => $overstock,
        ];
    }

    private function purchaseKpis(int $companyId, string $from, string $to): array
    {
        $orders = PurchaseOrder::where('company_id', $companyId)
            ->whereBetween('order_date', [$from, $to]);

        return [
            'orders_count' => (clone $orders)->count(),
            'total_amount' => round((float) (clone $orders)->sum('total_amount'), 2),
            'pending_orders' => (clone $orders)->whereIn('status', ['demande', 'commande'])->count(),
            'partial_receptions' => (clone $orders)->where('status', 'reception_partielle')->count(),
            'late_orders' => (clone $orders)
                ->whereIn('status', ['demande', 'commande'])
                ->whereNotNull('expected_date')
                ->whereDate('expected_date', '<', Carbon::now())
                ->count(),
        ];
    }

    /**
     * KPI RH (module 23 étendu) : effectif actif, masse salariale du dernier
     * mois validé, taux de retard — tout recalculé en direct, jamais stocké.
     */
    private function hrKpis(int $companyId, string $from, string $to): array
    {
        $activeEmployees = \App\Models\Employee::where('company_id', $companyId)->where('status', 'actif')->count();

        $lastPayroll = \App\Models\PayrollRun::where('company_id', $companyId)
            ->where('status', 'validee')
            ->orderByDesc('period')
            ->first();

        $attendanceInPeriod = \App\Models\Attendance::where('company_id', $companyId)
            ->whereBetween('date', [$from, $to])
            ->get();

        $latenessRate = $attendanceInPeriod->count() > 0
            ? round(($attendanceInPeriod->where('status', 'retard')->count() / $attendanceInPeriod->count()) * 100, 1)
            : 0;

        return [
            'active_employees' => $activeEmployees,
            'last_payroll_total' => $lastPayroll?->total_amount ? round((float) $lastPayroll->total_amount, 2) : null,
            'last_payroll_period' => $lastPayroll?->period,
            'lateness_rate_percent' => $latenessRate,
        ];
    }

    private function alertsSummary(int $companyId): array
    {
        $counts = Alert::where('company_id', $companyId)
            ->where('is_resolved', false)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return [
            'critique' => (int) ($counts['critique'] ?? 0),
            'elevee' => (int) ($counts['elevee'] ?? 0),
            'moyenne' => (int) ($counts['moyenne'] ?? 0),
            'faible' => (int) ($counts['faible'] ?? 0),
        ];
    }
}
