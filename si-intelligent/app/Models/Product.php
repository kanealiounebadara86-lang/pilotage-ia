<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'category_id', 'primary_supplier_id', 'reference', 'sku',
        'barcode', 'name', 'description', 'unit', 'purchase_price', 'sale_price',
        'cost', 'stock_min', 'stock_max', 'safety_stock', 'is_perishable',
        'image_path', 'is_active',
    ];

    protected $casts = [
        'is_perishable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function primarySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->withPivot(['price', 'average_lead_time_days', 'min_order_quantity'])
            ->withTimestamps();
    }

    public function stockLevel(): HasOne
    {
        return $this->hasOne(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function forecastResults(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }

    public function replenishmentRecommendations(): HasMany
    {
        return $this->hasMany(ReplenishmentRecommendation::class);
    }

    public function marginPercent(): float
    {
        if ($this->sale_price <= 0) {
            return 0.0;
        }

        return round((($this->sale_price - $this->cost) / $this->sale_price) * 100, 2);
    }
}
