<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Revenue;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /** Catégories de transactions qui sont des REMBOURSEMENTS (viennent en déduction du chiffre d'affaires). */
    private const REFUND_CATEGORIES = ['remboursement', 'annulation_vente'];

    /**
     * Encaissement client : c'est lui — et non la vente — qui fait entrer
     * le chiffre d'affaires dans les indicateurs financiers.
     */
    public function recordSaleCollection(Payment $payment, Sale $sale): FinancialTransaction
    {
        return FinancialTransaction::create([
            'company_id' => $sale->company_id,
            'type' => 'revenu',
            'amount' => $payment->amount,
            'category' => 'vente',
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'description' => "Encaissement vente {$sale->reference}",
        ]);
    }

    /** Remboursement d'argent à un client (retour ou annulation) : diminue le chiffre d'affaires encaissé. */
    public function recordSaleRefund(Payment $payment, Sale $sale, string $label): FinancialTransaction
    {
        return FinancialTransaction::create([
            'company_id' => $sale->company_id,
            'type' => 'depense',
            'amount' => abs((float) $payment->amount),
            'category' => 'remboursement',
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'description' => $label,
        ]);
    }

    /** Règlement d'un fournisseur : la dépense d'achat est constatée quand l'argent sort. */
    public function recordPurchasePaymentExpense(Payment $payment, PurchaseOrder $order): FinancialTransaction
    {
        return FinancialTransaction::create([
            'company_id' => $order->company_id,
            'type' => 'depense',
            'amount' => $payment->amount,
            'category' => 'achat',
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'transaction_date' => $payment->payment_date,
            'description' => "Règlement commande {$order->reference}",
        ]);
    }

    public function recordExpense(Expense $expense): FinancialTransaction
    {
        return FinancialTransaction::create([
            'company_id' => $expense->company_id,
            'type' => 'depense',
            'amount' => $expense->amount,
            'category' => $expense->category ?? 'depense_diverse',
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'transaction_date' => $expense->expense_date,
            'description' => $expense->label,
        ]);
    }

    public function recordRevenue(Revenue $revenue): FinancialTransaction
    {
        return FinancialTransaction::create([
            'company_id' => $revenue->company_id,
            'type' => 'revenu',
            'amount' => $revenue->amount,
            'category' => $revenue->category ?? 'revenu_divers',
            'reference_type' => Revenue::class,
            'reference_id' => $revenue->id,
            'transaction_date' => $revenue->revenue_date,
            'description' => $revenue->label,
        ]);
    }

    /**
     * Synthèse financière sur une période (module 18) : revenus, dépenses,
     * marge brute, calculés uniquement à partir de financial_transactions.
     */
    public function getSummary(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $to ?? Carbon::now()->toDateString();

        $base = FinancialTransaction::where('company_id', $companyId)->whereBetween('transaction_date', [$from, $to]);

        $grossRevenue = (float) (clone $base)->where('type', 'revenu')->sum('amount');
        $allExpenses = (float) (clone $base)->where('type', 'depense')->sum('amount');
        $refunds = (float) (clone $base)->where('type', 'depense')->whereIn('category', self::REFUND_CATEGORIES)->sum('amount');

        $collected = $this->getCollected($companyId, $from, $to);

        return [
            'period' => ['from' => $from, 'to' => $to],
            // Revenus = encaissements nets des remboursements ; dépenses hors remboursements.
            'revenue_total' => round($grossRevenue - $refunds, 2),
            'expense_total' => round($allExpenses - $refunds, 2),
            'net_result' => round($grossRevenue - $allExpenses, 2),
            'refunds_total' => round($refunds, 2),
            // Chiffre d'affaires ENCAISSÉ net et marge correspondante.
            'sales_revenue' => $collected['revenue'],
            'sales_margin' => $collected['margin'],
            'sales_margin_percent' => $collected['revenue'] > 0 ? round(($collected['margin'] / $collected['revenue']) * 100, 2) : 0,
            'receivables_open' => $this->getOpenReceivables($companyId),
        ];
    }

    /**
     * Chiffre d'affaires ENCAISSÉ sur la période : somme des paiements clients,
     * remboursements déduits (ils sont enregistrés en négatif). Une vente non
     * encaissée n'y figure pas. La marge est celle des ventes au prorata encaissé.
     */
    public function getCollected(int $companyId, string $from, string $to): array
    {
        $row = DB::table('payments')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.payable_id')->where('payments.payable_type', '=', Sale::class);
            })
            ->where('payments.company_id', $companyId)
            ->whereBetween('payments.payment_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as revenue,
                COALESCE(SUM(payments.amount * sales.margin_percent / 100), 0) as margin,
                COALESCE(SUM(CASE WHEN payments.amount < 0 THEN -payments.amount ELSE 0 END), 0) as refunds')
            ->first();

        return [
            'revenue' => round((float) $row->revenue, 2),
            'margin' => round((float) $row->margin, 2),
            'refunds' => round((float) $row->refunds, 2),
        ];
    }

    /** Reste à encaisser sur l'ensemble des ventes confirmées (net des retours). */
    public function getOpenReceivables(int $companyId): float
    {
        $paid = DB::table('payments')->where('payable_type', Sale::class)
            ->groupBy('payable_id')->selectRaw('payable_id, SUM(amount) as paid');

        $due = DB::table('sales')
            ->leftJoinSub($paid, 'p', 'p.payable_id', '=', 'sales.id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'confirmed')
            ->selectRaw('COALESCE(SUM(sales.total_amount - sales.returned_amount - COALESCE(p.paid, 0)), 0) as due')
            ->value('due');

        return round(max(0, (float) $due), 2);
    }

    /**
     * Rentabilité par produit (module 18) : CA, coût, marge, calculés à partir
     * des lignes de vente confirmées sur la période.
     */
    public function getProfitabilityByProduct(int $companyId, ?string $from = null, ?string $to = null)
    {
        $from = $from ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $to ?? Carbon::now()->toDateString();

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'confirmed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                SUM(sale_items.quantity) as quantity_sold,
                SUM(sale_items.line_total) as revenue,
                SUM(sale_items.unit_cost * sale_items.quantity) as cost,
                SUM(sale_items.line_total) - SUM(sale_items.unit_cost * sale_items.quantity) as margin
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('margin')
            ->get();
    }

    /**
     * Indicateurs financiers étendus (module 18 + module 14 "impact automatique
     * sur les KPI") : position de trésorerie, créances clients, dettes
     * fournisseurs — calculés à partir des soldes réels du plan comptable,
     * jamais saisis à la main. Complète getSummary() sans le remplacer.
     */
    public function getExtendedKpis(int $companyId): array
    {
        $accounts = \App\Models\ChartOfAccount::where('company_id', $companyId)->get()->keyBy('code');

        $cash = (float) (($accounts['512000']?->balance() ?? 0) + ($accounts['571000']?->balance() ?? 0));
        $receivables = (float) ($accounts['411000']?->balance() ?? 0);
        $payables = (float) ($accounts['401000']?->balance() ?? 0);

        // Résultat cumulé depuis le début de l'activité (pas seulement la période
        // affichée) : donne une vision de la rentabilité globale de l'entreprise.
        $allTimeGross = (float) FinancialTransaction::where('company_id', $companyId)->where('type', 'revenu')->sum('amount');
        $allTimeExpense = (float) FinancialTransaction::where('company_id', $companyId)->where('type', 'depense')->sum('amount');
        $allTimeRefunds = (float) FinancialTransaction::where('company_id', $companyId)->where('type', 'depense')->whereIn('category', self::REFUND_CATEGORIES)->sum('amount');
        $cumulativeResult = $allTimeGross - $allTimeExpense;
        $allTimeRevenue = $allTimeGross - $allTimeRefunds;
        $netMarginPercent = $allTimeRevenue > 0 ? round(($cumulativeResult / $allTimeRevenue) * 100, 1) : null;

        return [
            'cash_position' => round($cash, 2),
            'receivables' => round($receivables, 2),
            'payables' => round($payables, 2),
            'working_capital' => round($cash + $receivables - $payables, 2),
            'cumulative_result' => round($cumulativeResult, 2),
            'net_margin_percent' => $netMarginPercent,
        ];
    }

    /**
     * Répartition des dépenses par catégorie sur une période — utile pour
     * voir d'un coup d'œil où va l'argent (achats, paie, avances, loyer...).
     */
    public function getExpenseBreakdown(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? \Illuminate\Support\Carbon::now()->startOfMonth()->toDateString();
        $to = $to ?? \Illuminate\Support\Carbon::now()->toDateString();

        return FinancialTransaction::where('company_id', $companyId)
            ->where('type', 'depense')
            ->whereNotIn('category', self::REFUND_CATEGORIES)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category' => $row->category ?? 'autre', 'total' => round((float) $row->total, 2)])
            ->toArray();
    }

    /**
     * Tendance du chiffre d'affaires sur les N derniers mois — alimente le
     * graphique du tableau de bord financier.
     */
    public function getRevenueTrend(int $companyId, int $months = 6): array
    {
        $start = \Illuminate\Support\Carbon::now()->subMonths($months - 1)->startOfMonth();

        $salesRows = DB::table('payments')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.payable_id')->where('payments.payable_type', '=', Sale::class);
            })
            ->where('payments.company_id', $companyId)
            ->where('payments.payment_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(payments.payment_date, '%Y-%m') as month, SUM(payments.amount) as revenue, SUM(payments.amount * sales.margin_percent / 100) as margin")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $expenseRows = FinancialTransaction::where('company_id', $companyId)
            ->where('type', 'depense')
            ->whereNotIn('category', self::REFUND_CATEGORIES)
            ->where('transaction_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as expense")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i)->format('Y-m');
            $salesRow = $salesRows->get($month);
            $expenseRow = $expenseRows->get($month);
            $result[] = [
                'month' => $month,
                'revenue' => $salesRow ? round((float) $salesRow->revenue, 2) : 0,
                'margin' => $salesRow ? round((float) $salesRow->margin, 2) : 0,
                'expenses' => $expenseRow ? round((float) $expenseRow->expense, 2) : 0,
            ];
        }

        return $result;
    }

    public function getProfitabilityByCustomer(int $companyId, ?string $from = null, ?string $to = null)
    {
        $from = $from ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $to ?? Carbon::now()->toDateString();

        return DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.company_id', $companyId)
            ->where('sales.status', 'confirmed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('
                COALESCE(customers.id, 0) as customer_id,
                COALESCE(customers.name, "Client anonyme") as customer_name,
                COUNT(sales.id) as orders_count,
                SUM(sales.total_amount) as revenue,
                SUM(sales.margin_amount) as margin
            ')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')
            ->get();
    }
}
