<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('stock.view'), 403);

        return response()->json(
            Category::where('company_id', $request->user()->company_id)->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('stock.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($request->user()->hasPermission('stock.manage'), 403);
        abort_unless($category->company_id === $request->user()->company_id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($validated);

        return response()->json($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($request->user()->hasPermission('stock.manage'), 403);
        abort_unless($category->company_id === $request->user()->company_id, 403);
        abort_if($category->products()->exists(), 409, 'Impossible de supprimer une catégorie contenant des produits.');

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
