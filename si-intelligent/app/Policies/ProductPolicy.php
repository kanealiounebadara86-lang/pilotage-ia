<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Règle transverse : un utilisateur ne peut voir/gérer que les données
     * de sa propre entreprise (le système est multi-entreprise dès la conception).
     */
    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('stock.view') && $user->company_id === $product->company_id;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('stock.manage') && $user->company_id === $product->company_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('stock.manage') && $user->company_id === $product->company_id;
    }
}
