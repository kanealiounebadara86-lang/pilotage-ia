<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'name', 'description', 'start_date', 'end_date',
        'budget', 'target_product_id', 'status', 'user_id',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'campaign_id');
    }

    /**
     * Performance de la campagne : CA généré, marge, nombre de ventes
     * attribuées, et ROI = (marge générée - budget) / budget.
     * Tout est recalculé à partir des ventes réelles, jamais saisi à la main.
     */
    public function performance(): array
    {
        $sales = $this->sales()->where('status', 'confirmed')->get();

        // CA et marge ENCAISSÉS (nets des remboursements) : une vente non payée ne compte pas.
        $revenue = (float) $sales->sum(fn ($s) => $s->amountPaid());
        $margin = (float) $sales->sum(fn ($s) => $s->amountPaid() * $s->margin_percent / 100);
        $roi = $this->budget > 0 ? round((($margin - $this->budget) / $this->budget) * 100, 1) : null;

        return [
            'orders_count' => $sales->count(),
            'revenue' => round($revenue, 2),
            'margin' => round($margin, 2),
            'budget' => (float) $this->budget,
            'roi_percent' => $roi,
        ];
    }
}
