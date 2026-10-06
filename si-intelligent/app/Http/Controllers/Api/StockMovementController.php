<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Historique des mouvements, filtrable par produit — utile pour l'écran
     * "fiche produit" (module 13 : historique des mouvements).
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('stock.view'), 403);

        $query = \App\Models\StockMovement::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['product', 'user']);

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 30))
        );
    }

    /**
     * Mouvement manuel (correction, perte, inventaire, transfert).
     * Les mouvements d'entrée/sortie "normaux" viennent des ventes et des
     * réceptions d'achats, pas de cet endpoint.
     */
    public function store(StoreStockMovementRequest $request)
    {
        $product = Product::where('company_id', $request->user()->company_id)
            ->findOrFail($request->validated('product_id'));

        $movement = $this->stockService->recordMovement(
            product: $product,
            type: $request->validated('type'),
            quantitySigned: $request->validated('quantity'),
            user: $request->user(),
            reason: $request->validated('reason'),
        );

        return response()->json($movement->load('product.stockLevel'), 201);
    }
}
