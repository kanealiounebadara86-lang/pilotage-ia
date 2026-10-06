<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function storeForSale(StorePaymentRequest $request, Sale $sale)
    {
        abort_unless($sale->company_id === $request->user()->company_id, 403);

        $payment = $this->paymentService->recordSalePayment(
            $sale,
            $request->validated('amount'),
            $request->validated('method'),
            $request->validated('payment_date'),
            $request->user(),
        );

        return response()->json([
            'payment' => $payment,
            'sale' => $sale->fresh(),
            'balance_due' => $sale->fresh()->balanceDue(),
        ], 201);
    }

    /** Encaisse d'un coup toutes les ventes impayées (option : d'un seul client). */
    public function collectAll(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.manage'), 403);
        $data = $request->validate([
            'method' => ['required', 'in:especes,mobile_money,virement,cheque,carte'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->paymentService->collectAllUnpaid(
            $request->user()->company_id, $data['method'], $request->user(), $data['customer_id'] ?? null
        ));
    }

    public function storeForPurchase(StorePaymentRequest $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->company_id === $request->user()->company_id, 403);

        $payment = $this->paymentService->recordPurchasePayment(
            $purchaseOrder,
            $request->validated('amount'),
            $request->validated('method'),
            $request->validated('payment_date'),
            $request->user(),
        );

        return response()->json([
            'payment' => $payment,
            'purchase_order' => $purchaseOrder->fresh(),
            'balance_due' => $purchaseOrder->fresh()->balanceDue(),
        ], 201);
    }
}
