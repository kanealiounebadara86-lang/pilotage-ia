<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdvanceService
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly FinanceService $financeService,
    ) {
    }

    /**
     * Octroi d'une avance sur salaire : sort de la trésorerie immédiatement
     * (Débit Avances au personnel / Crédit Caisse), remboursée ensuite par
     * prélèvements mensuels sur la paie.
     */
    public function grantAdvance(Employee $employee, float $amount, float $monthlyInstallment, ?string $reason, User $user): EmployeeAdvance
    {
        return DB::transaction(function () use ($employee, $amount, $monthlyInstallment, $reason, $user) {
            $advance = EmployeeAdvance::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'amount' => $amount,
                'monthly_installment' => $monthlyInstallment,
                'remaining_amount' => $amount,
                'reason' => $reason,
                'granted_date' => now()->toDateString(),
                'status' => 'en_cours',
                'user_id' => $user->id,
            ]);

            $this->accountingService->createEntry(
                companyId: $employee->company_id,
                label: "Avance sur salaire — {$employee->fullName()}",
                lines: [
                    ['code' => '425000', 'debit' => $amount, 'credit' => 0, 'label' => 'Avance au personnel'],
                    ['code' => '571000', 'debit' => 0, 'credit' => $amount, 'label' => 'Sortie caisse'],
                ],
                sourceType: EmployeeAdvance::class,
                sourceId: $advance->id,
                user: $user,
            );

            \App\Models\FinancialTransaction::create([
                'company_id' => $employee->company_id,
                'type' => 'depense',
                'amount' => $amount,
                'category' => 'avance_personnel',
                'reference_type' => EmployeeAdvance::class,
                'reference_id' => $advance->id,
                'transaction_date' => now()->toDateString(),
                'description' => "Avance sur salaire — {$employee->fullName()}",
            ]);

            return $advance;
        });
    }

    /**
     * Calcule le montant à prélever ce mois-ci sur une avance en cours
     * (mensualité, plafonnée au solde restant) — utilisé par PayrollService.
     */
    public function nextInstallment(EmployeeAdvance $advance): float
    {
        return min($advance->monthly_installment, $advance->remaining_amount);
    }

    public function applyInstallment(EmployeeAdvance $advance, float $amount): void
    {
        $advance->decrement('remaining_amount', $amount);

        if ($advance->fresh()->remaining_amount <= 0.01) {
            $advance->update(['status' => 'soldee']);
        }
    }
}
