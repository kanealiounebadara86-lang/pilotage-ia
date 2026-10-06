<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'code', 'label', 'class', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function balance(): float
    {
        $totals = $this->lines()->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
        $debit = (float) ($totals->d ?? 0);
        $credit = (float) ($totals->c ?? 0);

        return in_array($this->class, ['charge', 'actif', 'tresorerie']) ? $debit - $credit : $credit - $debit;
    }
}
