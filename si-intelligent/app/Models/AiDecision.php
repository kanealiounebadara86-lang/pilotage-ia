<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'replenishment_recommendation_id', 'decided_by', 'final_decision', 'comment', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentRecommendation::class, 'replenishment_recommendation_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
