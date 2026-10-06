<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'contact_name', 'email', 'phone', 'address',
        'score_price', 'score_lead_time', 'score_quality', 'score_reliability',
        'score_global', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot(['price', 'average_lead_time_days', 'min_order_quantity'])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Recalcule le score global pondéré du fournisseur.
     * Pondérations par défaut (paramétrables) : prix 30%, délai 25%, qualité 20%, fiabilité 25%.
     */
    public function recalculateGlobalScore(array $weights = [
        'price' => 0.30, 'lead_time' => 0.25, 'quality' => 0.20, 'reliability' => 0.25,
    ]): float
    {
        $this->score_global =
            $this->score_price * $weights['price']
            + $this->score_lead_time * $weights['lead_time']
            + $this->score_quality * $weights['quality']
            + $this->score_reliability * $weights['reliability'];

        $this->save();

        return $this->score_global;
    }
}
