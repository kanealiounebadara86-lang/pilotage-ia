<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendationFactor extends Model
{
    use HasFactory;

    protected $fillable = [
        'replenishment_recommendation_id', 'label', 'weight', 'direction', 'detail',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentRecommendation::class, 'replenishment_recommendation_id');
    }
}
