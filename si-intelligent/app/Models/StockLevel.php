<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'warehouse_id', 'quantity_available', 'last_movement_at'];

    protected $casts = ['last_movement_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isBelowMinimum(): bool
    {
        return $this->quantity_available <= $this->product->stock_min;
    }

    public function isAboveMaximum(): bool
    {
        return $this->product->stock_max !== null
            && $this->quantity_available >= $this->product->stock_max;
    }
}
