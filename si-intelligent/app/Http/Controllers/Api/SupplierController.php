<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\SyncSupplierWeightsRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('purchases.view'), 403);

        $query = Supplier::query()->where('company_id', $request->user()->company_id);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(
            $query->orderByDesc('score_global')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        $supplier->recalculateGlobalScore();

        return response()->json($supplier, 201);
    }

    public function show(Request $request, Supplier $supplier)
    {
        $this->authorize('view', $supplier);

        return response()->json($supplier->load('products'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());
        $supplier->recalculateGlobalScore();

        return response()->json($supplier->fresh());
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return response()->json(['message' => 'Fournisseur supprimé.']);
    }

    /**
     * Recalcule le score du fournisseur avec des pondérations personnalisées.
     * Cf. cahier des charges section 11 : les poids (prix/délai/qualité/fiabilité)
     * doivent être modifiables par l'utilisateur.
     */
    public function recalculateScore(SyncSupplierWeightsRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $weights = $request->validated();

        abort_unless(
            abs(array_sum($weights) - 1.0) < 0.001,
            422,
            'La somme des pondérations doit être égale à 1 (100%).'
        );

        $score = $supplier->recalculateGlobalScore($weights);

        return response()->json([
            'supplier_id' => $supplier->id,
            'score_global' => $score,
            'weights_used' => $weights,
        ]);
    }
}
