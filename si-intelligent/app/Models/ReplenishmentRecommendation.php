<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReplenishmentRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'product_id', 'supplier_id', 'decision_type',
        'recommended_quantity', 'recommended_date', 'priority', 'confidence',
        'estimated_impact', 'status', 'reviewed_by',
    ];

    protected $casts = ['recommended_date' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function factors(): HasMany
    {
        return $this->hasMany(RecommendationFactor::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(AiDecision::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(AiFeedback::class);
    }
}
