<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'company_id', 'customer_id', 'user_id', 'reference', 'sale_date',
        'campaign_id', 'discount_total', 'tax_total', 'total_amount', 'total_cost',
        'margin_amount', 'margin_percent', 'payment_method', 'status', 'payment_status', 'returned_amount',
    ];

    protected $appends = ['amount_paid', 'balance_due'];

    protected $casts = ['sale_date' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function amountPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Valeur nette de la vente : total moins les marchandises retournées. */
    public function netTotal(): float
    {
        return round((float) $this->total_amount - (float) $this->returned_amount, 2);
    }

    public function balanceDue(): float
    {
        return round($this->netTotal() - $this->amountPaid(), 2);
    }

    public function getAmountPaidAttribute(): float
    {
        return $this->amountPaid();
    }

    public function getBalanceDueAttribute(): float
    {
        return $this->status === 'cancelled' ? 0.0 : max(0.0, $this->balanceDue());
    }
}
