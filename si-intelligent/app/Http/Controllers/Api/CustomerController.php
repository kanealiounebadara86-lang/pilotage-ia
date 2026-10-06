<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('sales.view'), 403);

        $query = Customer::query()->where('company_id', $request->user()->company_id);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json(
            $query->orderBy('name')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        // Statistiques simples issues des ventes : utiles pour la segmentation (module 10).
        $customer->loadCount('sales');
        // Chiffre d'affaires du client = ce qu'il a réellement payé, net des remboursements.
        $customer->setAttribute('total_revenue', round((float) \App\Models\Payment::where('payable_type', \App\Models\Sale::class)
            ->whereIn('payable_id', $customer->sales()->pluck('id'))->sum('amount'), 2));

        return response()->json($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return response()->json($customer->fresh());
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorize('delete', $customer);

        abort_if($customer->sales()->exists(), 409, 'Impossible de supprimer un client ayant des ventes enregistrées.');

        $customer->delete();

        return response()->json(['message' => 'Client supprimé.']);
    }
}
