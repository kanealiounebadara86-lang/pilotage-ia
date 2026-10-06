<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'forecast_id', 'product_id', 'period_date',
        'predicted_quantity', 'lower_bound', 'upper_bound',
    ];

    protected $casts = ['period_date' => 'date'];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(Forecast::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
