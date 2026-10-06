<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quoteService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $quotes = Quote::where('company_id', $request->user()->company_id)
            ->with(['customer', 'items.product'])
            ->orderByDesc('quote_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json($quotes);
    }

    public function store(StoreQuoteRequest $request)
    {
        $quote = $this->quoteService->createQuote($request->validated(), $request->user());

        return response()->json($quote, 201);
    }

    public function convert(Request $request, Quote $quote)
    {
        abort_unless($request->user()->hasPermission('sales.manage'), 403);
        abort_unless($quote->company_id === $request->user()->company_id, 403);

        $quote = $this->quoteService->convertToSale($quote, $request->user());

        return response()->json($quote);
    }

    public function updateStatus(Request $request, Quote $quote)
    {
        abort_unless($request->user()->hasPermission('sales.manage'), 403);
        abort_unless($quote->company_id === $request->user()->company_id, 403);

        $validated = $request->validate(['status' => ['required', 'in:envoye,accepte,refuse']]);
        $quote->update($validated);

        return response()->json($quote->fresh());
    }
}
