<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly SalesService $salesService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $query = Sale::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['customer', 'user', 'items.product']);

        if ($from = $request->query('date_from')) {
            $query->whereDate('sale_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('sale_date', '<=', $to);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->orderByDesc('sale_date')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreSaleRequest $request)
    {
        $sale = $this->salesService->createSale($request->validated(), $request->user());

        return response()->json($sale, 201);
    }

    public function show(Request $request, Sale $sale)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);
        abort_unless($sale->company_id === $request->user()->company_id, 403);

        return response()->json($sale->load(['customer', 'user', 'items.product']));
    }

    public function cancel(Request $request, Sale $sale)
    {
        abort_unless($request->user()->hasPermission('sales.manage'), 403);
        abort_unless($sale->company_id === $request->user()->company_id, 403);

        $sale = $this->salesService->cancelSale($sale, $request->user());

        return response()->json($sale);
    }
}
