<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchases.view') && $user->company_id === $supplier->company_id;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchases.manage') && $user->company_id === $supplier->company_id;
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('purchases.manage') && $user->company_id === $supplier->company_id;
    }
}
