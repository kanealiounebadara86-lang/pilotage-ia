<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EmployeeAdvanceController;
use App\Http\Controllers\Api\MarketingCampaignController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\ReplenishmentController;
use App\Http\Controllers\Api\HrInsightController;
use App\Http\Controllers\Api\OrchestratorController;
use App\Http\Controllers\Api\FinanceInsightController;
use App\Http\Controllers\Api\MarketingInsightController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);

    // Module Produits
    Route::apiResource('products', ProductController::class);

    // Module Catégories (support des produits)
    Route::apiResource('categories', CategoryController::class)->except(['show']);

    // Module Clients
    Route::apiResource('customers', CustomerController::class);

    // Module Fournisseurs
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/{supplier}/recalculate-score', [SupplierController::class, 'recalculateScore']);

    // Module Ventes
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);

    // Module Stocks
    Route::get('/stock-movements', [StockMovementController::class, 'index']);
    Route::post('/stock-movements', [StockMovementController::class, 'store']);

    // Module Achats
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);

    // Alertes intelligentes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);

    // Module Finance
    Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
    Route::get('/finance/summary', [FinanceController::class, 'summary']);
    Route::get('/finance/kpis', [FinanceController::class, 'kpis']);
    Route::get('/finance/profitability/products', [FinanceController::class, 'profitabilityByProduct']);
    Route::get('/finance/profitability/customers', [FinanceController::class, 'profitabilityByCustomer']);
    Route::post('/expenses', [FinanceController::class, 'storeExpense']);
    Route::post('/revenues', [FinanceController::class, 'storeRevenue']);

    // Tableau de bord exécutif
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Module Comptabilité
    Route::get('/accounting/accounts', [AccountingController::class, 'accounts']);
    Route::get('/accounting/entries', [AccountingController::class, 'entries']);
    Route::get('/accounting/ledger/{code}', [AccountingController::class, 'ledger']);

    // Module GRH
    Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
    Route::get('/payroll-runs', [PayrollController::class, 'index']);
    Route::post('/payroll-runs', [PayrollController::class, 'store']);
    Route::get('/payroll-runs/{payrollRun}', [PayrollController::class, 'show']);
    Route::post('/payroll-runs/{payrollRun}/validate', [PayrollController::class, 'validatePayroll']);
    Route::post('/voice/interpret', [\App\Http\Controllers\Api\VoiceCommandController::class, 'interpret']);
    Route::post('/voice/execute', [\App\Http\Controllers\Api\VoiceCommandController::class, 'execute']);
    Route::post('/sales/collect-all', [PaymentController::class, 'collectAll']);
    Route::post('/payroll-runs/{payrollRun}/pay', [PayrollController::class, 'payPayroll']);
    Route::delete('/payroll-runs/{payrollRun}', [PayrollController::class, 'destroy']);
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::post('/leave-requests/{leaveRequest}/review', [LeaveRequestController::class, 'review']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/summary', [AttendanceController::class, 'summary']);
    Route::get('/attendances/today', [AttendanceController::class, 'today']);
    Route::post('/attendances/punch', [AttendanceController::class, 'punch']);
    Route::post('/attendances/punch-all', [AttendanceController::class, 'punchAll']);
    Route::patch('/attendances/{attendance}/overtime', [AttendanceController::class, 'setOvertime']);
    Route::post('/attendances', [AttendanceController::class, 'store']);
    Route::get('/employee-advances', [EmployeeAdvanceController::class, 'index']);
    Route::post('/employee-advances', [EmployeeAdvanceController::class, 'store']);

    // Module Marketing
    Route::get('/campaigns', [MarketingCampaignController::class, 'index']);
    Route::post('/campaigns', [MarketingCampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [MarketingCampaignController::class, 'show']);
    Route::put('/campaigns/{campaign}/status', [MarketingCampaignController::class, 'updateStatus']);

    // Module Devis
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert']);
    Route::put('/quotes/{quote}/status', [QuoteController::class, 'updateStatus']);

    // Module Multi-entrepôts
    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::post('/warehouses', [WarehouseController::class, 'store']);
    Route::post('/stock-transfers', [WarehouseController::class, 'transfer']);

    // Module Retours / SAV
    Route::get('/sale-returns', [SaleReturnController::class, 'index']);
    Route::post('/sale-returns', [SaleReturnController::class, 'store']);

    // Module IA — Prévision des ventes (Phase 5)
    Route::post('/ai/forecast', [ForecastController::class, 'store']);
    Route::post('/ai/forecast/bulk', [ForecastController::class, 'runBulk']);
    Route::get('/ai/forecast/history/{product}', [ForecastController::class, 'history']);

    // Module IA — Réapprovisionnement (Phase 6)
    Route::post('/ai/replenishment/run', [ReplenishmentController::class, 'run']);
    Route::get('/ai/replenishment', [ReplenishmentController::class, 'index']);
    Route::post('/ai/replenishment/{recommendation}/decide', [ReplenishmentController::class, 'decide']);
    Route::get('/ai/replenishment/{recommendation}/draft-message', [ReplenishmentController::class, 'draftMessage']);
    Route::get('/ai/replenishment/{recommendation}/explain', [ReplenishmentController::class, 'explain']);

    // Module IA — RH
    Route::post('/ai/hr/insights', [HrInsightController::class, 'run']);

    // Module IA — Finance
    Route::post('/ai/finance/insights', [FinanceInsightController::class, 'run']);

    // Module IA — Marketing
    Route::post('/ai/marketing/insights', [MarketingInsightController::class, 'run']);

    // Orchestrateur IA
    Route::post('/ai/orchestrate', [OrchestratorController::class, 'run']);

    // Assistant conversationnel (Phase 8)
    Route::post('/ai/assistant', [AssistantController::class, 'ask']);

    // Paiements (encaissements clients / règlements fournisseurs)
    Route::post('/sales/{sale}/payments', [PaymentController::class, 'storeForSale']);
    Route::post('/purchase-orders/{purchaseOrder}/payments', [PaymentController::class, 'storeForPurchase']);

    // Traçabilité
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Les routes des modules Optimisation, XAI et LLM
    // seront ajoutées aux phases suivantes (6 à 8), via le service Python.
});
