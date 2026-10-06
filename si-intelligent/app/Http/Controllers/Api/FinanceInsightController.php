<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinanceInsightService;
use Illuminate\Http\Request;

class FinanceInsightController extends Controller
{
    public function __construct(private readonly FinanceInsightService $financeInsightService)
    {
    }

    public function run(Request $request)
    {
        abort_unless($request->user()->hasPermission('finance.view'), 403);

        return response()->json($this->financeInsightService->analyze($request->user()->company_id));
    }
}
