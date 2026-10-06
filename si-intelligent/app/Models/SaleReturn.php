<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'sale_id', 'sale_item_id', 'user_id',
        'quantity', 'reason', 'restocked', 'refund_amount',
    ];

    protected $casts = ['restocked' => 'boolean'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
