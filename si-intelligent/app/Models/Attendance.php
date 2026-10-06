<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'employee_id', 'date', 'clock_in', 'clock_out',
        'hours_worked', 'overtime_hours', 'overtime_suggested', 'overtime_validated_at', 'status', 'note',
    ];

    protected $casts = ['date' => 'date', 'overtime_validated_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
