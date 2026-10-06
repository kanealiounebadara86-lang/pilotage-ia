<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'replenishment_recommendation_id', 'user_id', 'feedback_type', 'comment',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentRecommendation::class, 'replenishment_recommendation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
