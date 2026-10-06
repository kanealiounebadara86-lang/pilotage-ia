<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ReplenishmentRecommendation;
use App\Services\AiGatewayService;
use Illuminate\Http\Request;

class ReplenishmentController extends Controller
{
    public function __construct(
        private readonly AiGatewayService $aiGateway,
        private readonly \App\Services\PurchaseService $purchaseService,
        private readonly \App\Services\SupplierMessageService $supplierMessageService,
        private readonly \App\Services\ExplanationService $explanationService,
    ) {
    }

    /**
     * Lance l'IA de réapprovisionnement sur un périmètre choisi : un produit,
     * une liste de produits, ou "all" pour tous les produits actifs de
     * l'entreprise — l'orchestrateur (OrchestratorController) réutilise
     * cette même méthode.
     */
    public function run(Request $request)
    {
        abort_unless($request->user()->hasPermission('ai.view_recommendations') || $request->user()->hasPermission('stock.manage'), 403);

        $products = $this->resolveProducts($request);
        $results = [];

        foreach ($products as $product) {
            $results[] = $this->runForProduct($product, $request->user());
        }

        return response()->json(['count' => count($results), 'results' => $results]);
    }

    public function runForProduct(Product $product, $user): array
    {
        $result = $this->aiGateway->replenishment($product->id);

        if (($result['status'] ?? null) !== 'ok') {
            return ['product_id' => $product->id, 'product_name' => $product->name, 'status' => 'no_data', 'message' => $result['message'] ?? 'Pas assez de données.'];
        }

        $recommendation = ReplenishmentRecommendation::create([
            'company_id' => $user->company_id,
            'product_id' => $product->id,
            'supplier_id' => $result['supplier_id'],
            'decision_type' => $result['decision_type'],
            'recommended_quantity' => $result['recommended_quantity'],
            'recommended_date' => now()->addDays($result['recommended_in_days'])->toDateString(),
            'priority' => $result['priority'],
            'confidence' => $result['confidence'],
            'estimated_impact' => $result['estimated_impact'],
            'status' => 'proposee',
        ]);

        foreach ($result['factors'] as $factor) {
            $recommendation->factors()->create([
                'label' => $factor['label'],
                'weight' => $factor['weight'],
                'direction' => $factor['direction'],
                'detail' => $factor['detail'] ?? null,
            ]);
        }

        return [
            'status' => 'ok',
            'recommendation_id' => $recommendation->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'decision_type' => $result['decision_type'],
            'recommended_quantity' => $result['recommended_quantity'],
            'priority' => $result['priority'],
            'confidence' => $result['confidence'],
            'factors' => $result['factors'],
        ];
    }

    /**
     * Historique des recommandations, avec leur statut (proposée, acceptée,
     * refusée...) — module 25 du cahier des charges.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('ai.view_recommendations'), 403);

        $query = ReplenishmentRecommendation::where('company_id', $request->user()->company_id)
            ->with(['product', 'supplier', 'factors']);

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 30))
        );
    }

    /**
     * Le décideur accepte, modifie ou refuse une recommandation — le retour
     * humain est conservé (module 26 : feedback humain).
     */
    /**
     * Décision humaine sur une recommandation IA. Si acceptée ET qu'un
     * fournisseur est identifié, un VRAI brouillon de commande fournisseur
     * est créé automatiquement (statut "demande", is_ai_generated=true) —
     * mais ce n'est possible qu'APRÈS ce clic explicite de validation, jamais
     * avant. Le brouillon reste ensuite à valider/envoyer par un humain
     * comme n'importe quelle commande (module Achats).
     */
    public function decide(Request $request, ReplenishmentRecommendation $recommendation)
    {
        abort_unless($request->user()->hasPermission('ai.decide'), 403);
        abort_unless($recommendation->company_id === $request->user()->company_id, 403);

        $validated = $request->validate(['status' => ['required', 'in:acceptee,modifiee,refusee']]);

        $recommendation->update(['status' => $validated['status'], 'reviewed_by' => $request->user()->id]);

        $createdOrder = null;
        if ($validated['status'] === 'acceptee' && $recommendation->supplier_id && $recommendation->recommended_quantity > 0) {
            $order = $this->purchaseService->createPurchaseOrder([
                'supplier_id' => $recommendation->supplier_id,
                'items' => [['product_id' => $recommendation->product_id, 'quantity' => $recommendation->recommended_quantity]],
            ], $request->user(), isAiGenerated: true);

            $recommendation->update(['status' => 'executee']);
            $createdOrder = $order;
        }

        \App\Models\AiDecision::create([
            'replenishment_recommendation_id' => $recommendation->id,
            'decided_by' => $request->user()->id,
            'final_decision' => $validated['status'],
            'decided_at' => now(),
        ]);

        return response()->json([
            'recommendation' => $recommendation->fresh(),
            'purchase_order' => $createdOrder,
        ]);
    }

    /**
     * Génère un brouillon de message pour contacter le fournisseur au sujet
     * d'une recommandation — jamais envoyé automatiquement, uniquement
     * affiché pour copier/envoyer manuellement.
     */
    /**
     * Explication en langage naturel de la recommandation (Phase 7 - XAI).
     */
    public function explain(Request $request, ReplenishmentRecommendation $recommendation)
    {
        abort_unless($recommendation->company_id === $request->user()->company_id, 403);

        return response()->json(['explanation' => $this->explanationService->explainRecommendation($recommendation)]);
    }

    public function draftMessage(Request $request, ReplenishmentRecommendation $recommendation)
    {
        abort_unless($recommendation->company_id === $request->user()->company_id, 403);
        abort_unless($recommendation->supplier_id, 422, 'Aucun fournisseur identifié pour cette recommandation.');

        $message = $this->supplierMessageService->draftOrderMessage(
            $recommendation->supplier,
            $recommendation->product,
            $recommendation->recommended_quantity,
        );

        return response()->json($message);
    }

    private function resolveProducts(Request $request)
    {
        $companyId = $request->user()->company_id;

        if ($request->input('scope') === 'all') {
            return Product::where('company_id', $companyId)->where('is_active', true)->get();
        }

        $ids = $request->input('product_ids', []);
        abort_if(empty($ids), 422, 'Précise "scope: all" ou une liste "product_ids".');

        return Product::where('company_id', $companyId)->whereIn('id', $ids)->get();
    }
}
