<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MlModel extends Model
{
    use HasFactory;

    protected $table = 'ml_models';

    protected $fillable = [
        'company_id', 'type', 'target', 'version', 'trained_at',
        'training_data_range', 'metrics', 'parameters', 'storage_path', 'is_active',
    ];

    protected $casts = [
        'trained_at' => 'datetime',
        'training_data_range' => 'array',
        'metrics' => 'array',
        'parameters' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class);
    }
}
