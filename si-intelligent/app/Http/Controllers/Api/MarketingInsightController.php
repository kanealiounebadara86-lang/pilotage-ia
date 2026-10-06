<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketingInsightService;
use Illuminate\Http\Request;

class MarketingInsightController extends Controller
{
    public function __construct(private readonly MarketingInsightService $marketingInsightService)
    {
    }

    public function run(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        return response()->json($this->marketingInsightService->suggestCampaigns($request->user()->company_id));
    }
}
