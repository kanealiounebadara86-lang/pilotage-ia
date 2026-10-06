<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SupplierPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Product::class => ProductPolicy::class,
        Customer::class => CustomerPolicy::class,
        Supplier::class => SupplierPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
