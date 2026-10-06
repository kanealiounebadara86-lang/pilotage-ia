<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'company_id', 'department_id', 'user_id', 'first_name', 'last_name', 'position',
        'phone', 'email', 'hire_date', 'base_salary', 'status', 'leave_balance_days', 'hourly_rate',
    ];

    protected $casts = ['hire_date' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    /**
     * Taux horaire effectif : celui saisi explicitement, ou calculé depuis
     * le salaire de base sur la base de 173,33h/mois (durée légale moyenne).
     */
    public function effectiveHourlyRate(): float
    {
        return $this->hourly_rate ?? round($this->base_salary / 173.33, 2);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
