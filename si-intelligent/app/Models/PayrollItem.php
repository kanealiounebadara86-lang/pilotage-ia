<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'base_salary', 'overtime_amount',
        'bonuses', 'unpaid_leave_deduction', 'social_contributions',
        'advance_deduction', 'deductions', 'net_amount',
    ];

    protected $casts = [
        'base_salary' => 'float',
        'overtime_amount' => 'float',
        'bonuses' => 'float',
        'unpaid_leave_deduction' => 'float',
        'social_contributions' => 'float',
        'advance_deduction' => 'float',
        'deductions' => 'float',
        'net_amount' => 'float',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function grossSalary(): float
    {
        return round($this->base_salary - $this->unpaid_leave_deduction + $this->overtime_amount + $this->bonuses, 2);
    }
}
