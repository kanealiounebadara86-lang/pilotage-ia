<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForecastRequest;
use App\Models\Forecast;
use App\Models\MlModel;
use App\Models\Product;
use App\Services\AiGatewayService;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(private readonly AiGatewayService $aiGateway)
    {
    }

    /**
     * Lance une prévision pour un produit unique — comportement historique,
     * conservé pour la page /forecast existante.
     */
    public function store(StoreForecastRequest $request)
    {
        $product = Product::where('company_id', $request->user()->company_id)
            ->findOrFail($request->validated('product_id'));

        $result = $this->runForProduct($product, $request->validated('horizon_days', 14));

        if (($result['status'] ?? null) === 'no_data') {
            return response()->json($result, 422);
        }

        return response()->json($result, 201);
    }

    /**
     * Lance la prévision sur un périmètre : "scope: all" (tous les produits
     * actifs) ou une liste "product_ids" — réutilisé par l'orchestrateur.
     */
    public function runBulk(Request $request)
    {
        abort_unless($request->user()->hasPermission('ai.view_recommendations') || $request->user()->hasPermission('sales.view'), 403);

        $companyId = $request->user()->company_id;

        $products = $request->input('scope') === 'all'
            ? Product::where('company_id', $companyId)->where('is_active', true)->get()
            : Product::where('company_id', $companyId)->whereIn('id', $request->input('product_ids', []))->get();

        $horizon = $request->integer('horizon_days', 14);
        $results = [];

        foreach ($products as $product) {
            $results[] = $this->runForProduct($product, $horizon);
        }

        return response()->json(['count' => count($results), 'results' => $results]);
    }

    public function runForProduct(Product $product, int $horizon): array
    {
        $result = $this->aiGateway->forecast($product->id, $horizon);

        if (($result['status'] ?? null) === 'no_data') {
            return ['status' => 'no_data', 'product_id' => $product->id, 'product_name' => $product->name, 'message' => $result['message'] ?? "Pas assez d'historique."];
        }

        $mlModel = null;
        if (! empty($result['model_version'])) {
            $mlModel = MlModel::create([
                'company_id' => $product->company_id,
                'type' => $result['best_model'],
                'target' => 'sales_forecast',
                'version' => $result['model_version'],
                'trained_at' => now(),
                'metrics' => $result['evaluations'][$result['best_model']] ?? null,
                'parameters' => $result['feature_importances'] ?? null,
                'storage_path' => $result['model_version'],
                'is_active' => true,
            ]);
        }

        $forecast = Forecast::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'ml_model_id' => $mlModel?->id,
            'horizon_days' => $horizon,
            'frequency' => 'daily',
            'generated_at' => now()->toDateString(),
            'status' => 'termine',
        ]);

        foreach ($result['predictions'] as $point) {
            $forecast->results()->create([
                'product_id' => $product->id,
                'period_date' => $point['date'],
                'predicted_quantity' => $point['quantity'],
                'lower_bound' => $point['lower_bound'],
                'upper_bound' => $point['upper_bound'],
            ]);
        }

        return [
            'status' => 'ok',
            'forecast_id' => $forecast->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'best_model' => $result['best_model'],
            'history_days_used' => $result['history_days_used'],
            'evaluations' => $result['evaluations'],
            'predictions' => $result['predictions'],
            'warning' => $result['warning'] ?? null,
        ];
    }

    public function history(Request $request, Product $product)
    {
        abort_unless($product->company_id === $request->user()->company_id, 403);

        $forecasts = Forecast::where('product_id', $product->id)
            ->with('results', 'mlModel')
            ->orderByDesc('generated_at')
            ->paginate(10);

        return response()->json($forecasts);
    }
}
