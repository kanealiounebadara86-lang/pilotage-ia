<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Liste les produits de l'entreprise de l'utilisateur connecté,
     * avec filtres simples (recherche, catégorie, actif/inactif).
     */
    public function index(Request $request)
    {
        $this->authorizePermission($request, 'stock.view');

        $query = Product::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['category', 'primarySupplier', 'stockLevel']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(
            $query->orderBy('name')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create([
            ...$request->validated(),
            'reference' => $request->validated('reference') ?: $this->generateReference($request->user()->company_id),
            'company_id' => $request->user()->company_id,
        ]);

        // Chaque produit démarre avec un stock disponible à zéro,
        // alimenté ensuite par les mouvements de stock (module 13).
        StockLevel::create([
            'product_id' => $product->id,
            'quantity_available' => 0,
        ]);

        return response()->json($product->load('stockLevel'), 201);
    }

    public function show(Request $request, Product $product)
    {
        $this->authorize('view', $product);

        return response()->json(
            $product->load(['category', 'primarySupplier', 'suppliers', 'stockLevel'])
        );
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json($product->fresh(['category', 'primarySupplier', 'stockLevel']));
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorize('delete', $product);

        // Soft delete : on ne perd jamais l'historique (ventes, mouvements de stock
        // passés) rattaché à ce produit.
        $product->delete();

        return response()->json(['message' => 'Produit désactivé.']);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, "Permission '{$permission}' requise.");
    }

    /**
     * Référence auto-générée au format PRD-0001, PRD-0002... — évite les
     * doublons, les formats incohérents, et la saisie manuelle source
     * d'erreurs (le champ n'est donc plus proposé dans le formulaire).
     */
    private function generateReference(int $companyId): string
    {
        $count = Product::withTrashed()->where('company_id', $companyId)->count();

        do {
            $count++;
            $candidate = 'PRD-'.str_pad($count, 4, '0', STR_PAD_LEFT);
        } while (Product::where('reference', $candidate)->exists());

        return $candidate;
    }
}
