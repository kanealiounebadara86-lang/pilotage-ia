<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreRevenueRequest;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\Revenue;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $financeService)
    {
    }

    /**
     * Historique des transactions financières (revenus + dépenses), toutes
     * générées automatiquement par les ventes, les réceptions d'achats,
     * ou saisies manuellement via /expenses et /revenues.
     */
    public function transactions(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        $query = FinancialTransaction::where('company_id', $request->user()->company_id);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('date_from')) {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('transaction_date', '<=', $to);
        }

        return response()->json(
            $query->orderByDesc('transaction_date')->paginate($request->integer('per_page', 30))
        );
    }

    public function summary(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        return response()->json(
            $this->financeService->getSummary(
                $request->user()->company_id,
                $request->query('date_from'),
                $request->query('date_to'),
            )
        );
    }

    public function profitabilityByProduct(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        return response()->json(
            $this->financeService->getProfitabilityByProduct(
                $request->user()->company_id,
                $request->query('date_from'),
                $request->query('date_to'),
            )
        );
    }

    public function kpis(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        $companyId = $request->user()->company_id;

        return response()->json([
            ...$this->financeService->getExtendedKpis($companyId),
            'expense_breakdown' => $this->financeService->getExpenseBreakdown($companyId, $request->query('date_from'), $request->query('date_to')),
            'revenue_trend' => $this->financeService->getRevenueTrend($companyId, 6),
        ]);
    }

    public function profitabilityByCustomer(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        return response()->json(
            $this->financeService->getProfitabilityByCustomer(
                $request->user()->company_id,
                $request->query('date_from'),
                $request->query('date_to'),
            )
        );
    }

    public function storeExpense(StoreExpenseRequest $request)
    {
        $expense = Expense::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
        ]);

        $this->financeService->recordExpense($expense);

        return response()->json($expense, 201);
    }

    public function storeRevenue(StoreRevenueRequest $request)
    {
        $revenue = Revenue::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
        ]);

        $this->financeService->recordRevenue($revenue);

        return response()->json($revenue, 201);
    }
}
