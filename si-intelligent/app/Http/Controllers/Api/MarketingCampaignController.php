<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Models\MarketingCampaign;
use Illuminate\Http\Request;

class MarketingCampaignController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $campaigns = MarketingCampaign::where('company_id', $request->user()->company_id)
            ->with('targetProduct')
            ->orderByDesc('start_date')
            ->get();

        return response()->json(
            $campaigns->map(fn ($c) => [
                ...$c->toArray(),
                'performance' => $c->performance(),
            ])
        );
    }

    public function store(StoreCampaignRequest $request)
    {
        $campaign = MarketingCampaign::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'status' => 'planifiee',
        ]);

        return response()->json($campaign, 201);
    }

    public function show(Request $request, MarketingCampaign $campaign)
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        return response()->json([
            ...$campaign->load('targetProduct', 'sales.customer')->toArray(),
            'performance' => $campaign->performance(),
        ]);
    }

    public function updateStatus(Request $request, MarketingCampaign $campaign)
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $validated = $request->validate(['status' => ['required', 'in:planifiee,active,terminee']]);
        $campaign->update($validated);

        return response()->json($campaign->fresh());
    }
}
