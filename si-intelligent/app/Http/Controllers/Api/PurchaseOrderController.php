<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseService $purchaseService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('purchases.view'), 403);

        $query = PurchaseOrder::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['supplier', 'items.product']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        return response()->json(
            $query->orderByDesc('order_date')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $order = $this->purchaseService->createPurchaseOrder($request->validated(), $request->user());

        return response()->json($order, 201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($request->user()->hasPermission('purchases.view'), 403);
        abort_unless($purchaseOrder->company_id === $request->user()->company_id, 403);

        return response()->json($purchaseOrder->load(['supplier', 'items.product', 'receipts']));
    }

    /**
     * Enregistre une réception (partielle ou complète) — met à jour le stock
     * et le statut de la commande automatiquement (module 14).
     */
    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->company_id === $request->user()->company_id, 403);

        $order = $this->purchaseService->receiveOrder(
            $purchaseOrder,
            $request->validated('lines'),
            $request->user(),
            $request->validated('notes')
        );

        return response()->json($order);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($request->user()->hasPermission('purchases.manage'), 403);
        abort_unless($purchaseOrder->company_id === $request->user()->company_id, 403);
        abort_if(
            in_array($purchaseOrder->status, ['reception_partielle', 'reception_complete']),
            409,
            'Impossible d\'annuler une commande déjà partiellement ou totalement reçue.'
        );

        $purchaseOrder->update(['status' => 'annulee']);

        return response()->json($purchaseOrder->fresh());
    }
}
