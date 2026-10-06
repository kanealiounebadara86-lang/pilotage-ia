<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'product_id', 'ml_model_id', 'horizon_days',
        'frequency', 'generated_at', 'status',
    ];

    protected $casts = ['generated_at' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class, 'ml_model_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ForecastResult::class);
    }
}
