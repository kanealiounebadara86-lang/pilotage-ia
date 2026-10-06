<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouseService)
    {
    }

    public function index(Request $request)
    {
        return response()->json(
            Warehouse::where('company_id', $request->user()->company_id)->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('stock.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        $warehouse = Warehouse::create([...$validated, 'company_id' => $request->user()->company_id]);

        return response()->json($warehouse, 201);
    }

    public function transfer(StoreStockTransferRequest $request)
    {
        $data = $request->validated();
        $product = Product::where('company_id', $request->user()->company_id)->findOrFail($data['product_id']);
        $from = Warehouse::where('company_id', $request->user()->company_id)->findOrFail($data['from_warehouse_id']);
        $to = Warehouse::where('company_id', $request->user()->company_id)->findOrFail($data['to_warehouse_id']);

        $this->warehouseService->transfer($product, $from, $to, $data['quantity'], $request->user());

        return response()->json(['message' => 'Transfert enregistré.'], 201);
    }
}
