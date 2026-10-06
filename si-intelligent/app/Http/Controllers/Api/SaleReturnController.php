<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleReturnRequest;
use App\Models\SaleItem;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returnService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $returns = \App\Models\SaleReturn::where('company_id', $request->user()->company_id)
            ->with(['saleItem.product', 'sale'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($returns);
    }

    public function store(StoreSaleReturnRequest $request)
    {
        $saleItem = SaleItem::whereHas('sale', fn ($q) => $q->where('company_id', $request->user()->company_id))
            ->findOrFail($request->validated('sale_item_id'));

        $return = $this->returnService->processReturn(
            $saleItem,
            $request->validated('quantity'),
            $request->validated('reason'),
            $request->boolean('restock', true),
            $request->user(),
            $request->validated('refund_method') ?? 'especes',
        );

        return response()->json($return, 201);
    }
}
