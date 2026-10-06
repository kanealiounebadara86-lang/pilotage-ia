<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    // Taux de cotisation sociale salariale simplifié (part employé uniquement).
    // À ajuster selon le régime réel (CNSS, IPRES...) si le mémoire doit être
    // précis sur ce point — volontairement simple ici.
    private const SOCIAL_CONTRIBUTION_RATE = 0.06;
    private const OVERTIME_MAJORATION = 1.25; // +25% par heure supplémentaire

    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly AttendanceService $attendanceService,
    ) {
    }

    /**
     * Génère une campagne de paie brouillon : un bulletin détaillé par
     * employé actif, prenant en compte heures supplémentaires (pointage),
     * congés sans solde (calendrier d'absence), avances en cours, et
     * cotisations sociales — exactement la chaîne demandée :
     * salaire → absences → heures sup → primes → retenues → avances →
     * cotisations → net.
     */
    public function generateDraft(int $companyId, string $period, User $user): PayrollRun
    {
        abort_if(
            PayrollRun::where('company_id', $companyId)->where('period', $period)->exists(),
            422,
            "Une paie existe déjà pour la période {$period}."
        );

        return DB::transaction(function () use ($companyId, $period, $user) {
            $run = PayrollRun::create([
                'company_id' => $companyId,
                'period' => $period,
                'status' => 'brouillon',
                'created_by' => $user->id,
            ]);

            $employees = Employee::where('company_id', $companyId)->where('status', 'actif')->get();
            $total = 0;

            foreach ($employees as $employee) {
                $item = $this->buildPayrollItem($employee, $period);
                $run->items()->create($item);
                $total += $item['net_amount'];
            }

            $run->update(['total_amount' => $total]);

            return $run->load('items.employee');
        });
    }

    private function buildPayrollItem(Employee $employee, string $period): array
    {
        $summary = $this->attendanceService->monthlySummary($employee, $period);

        $dailyRate = $employee->base_salary / 30; // approximation simple (mois de 30 jours)
        $hourlyRate = $employee->effectiveHourlyRate();

        $overtimeAmount = round($summary['overtime_hours'] * $hourlyRate * self::OVERTIME_MAJORATION, 2);
        $unpaidLeaveDeduction = round($summary['leave_days'] * $dailyRate, 2);

        $bonuses = 0; // ajustable manuellement après génération, avant validation
        $grossSalary = round($employee->base_salary - $unpaidLeaveDeduction + $overtimeAmount + $bonuses, 2);

        $socialContributions = round($grossSalary * self::SOCIAL_CONTRIBUTION_RATE, 2);

        $advanceDeduction = 0;
        $activeAdvance = EmployeeAdvance::where('employee_id', $employee->id)->where('status', 'en_cours')->first();
        if ($activeAdvance) {
            $advanceDeduction = min($activeAdvance->monthly_installment, $activeAdvance->remaining_amount);
        }

        $netAmount = round($grossSalary - $socialContributions - $advanceDeduction, 2);

        return [
            'employee_id' => $employee->id,
            'base_salary' => $employee->base_salary,
            'overtime_amount' => $overtimeAmount,
            'bonuses' => $bonuses,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'social_contributions' => $socialContributions,
            'advance_deduction' => $advanceDeduction,
            'deductions' => round($unpaidLeaveDeduction + $socialContributions + $advanceDeduction, 2),
            'net_amount' => $netAmount,
        ];
    }

    /**
     * Valide la paie : verrouille les montants, applique les prélèvements
     * d'avances (réduit le solde restant de chaque avance concernée), et
     * génère une écriture comptable détaillée (charges brutes / net à payer /
     * cotisations à verser / avances remboursées) plus la dépense financière.
     */
    public function validate(PayrollRun $run, User $user): PayrollRun
    {
        return DB::transaction(function () use ($run, $user) {
            abort_if($run->status !== 'brouillon', 409, 'Seule une paie en brouillon peut être validée.');

            $run->load('items.employee');

            $grossTotal = 0;
            $netTotal = 0;
            $contributionsTotal = 0;
            $advanceTotal = 0;

            foreach ($run->items as $item) {
                $gross = $item->base_salary - $item->unpaid_leave_deduction + $item->overtime_amount + $item->bonuses;
                $grossTotal += $gross;
                $netTotal += $item->net_amount;
                $contributionsTotal += $item->social_contributions;
                $advanceTotal += $item->advance_deduction;

                if ($item->advance_deduction > 0) {
                    $advance = EmployeeAdvance::where('employee_id', $item->employee_id)->where('status', 'en_cours')->first();
                    if ($advance) {
                        $advance->decrement('remaining_amount', $item->advance_deduction);
                        if ($advance->fresh()->remaining_amount <= 0.01) {
                            $advance->update(['status' => 'soldee']);
                        }
                    }
                }
            }

            $run->update(['status' => 'validee', 'payment_date' => now()->toDateString(), 'total_amount' => $netTotal]);

            $lines = [
                ['code' => '621000', 'debit' => round($grossTotal, 2), 'credit' => 0, 'label' => 'Charges de personnel (brut)'],
                ['code' => '421000', 'debit' => 0, 'credit' => round($netTotal, 2), 'label' => 'Net à payer aux employés'],
            ];
            if ($contributionsTotal > 0) {
                $lines[] = ['code' => '431000', 'debit' => 0, 'credit' => round($contributionsTotal, 2), 'label' => 'Cotisations sociales à verser'];
            }
            if ($advanceTotal > 0) {
                $lines[] = ['code' => '425000', 'debit' => 0, 'credit' => round($advanceTotal, 2), 'label' => 'Remboursement avances sur salaire'];
            }

            $this->accountingService->createEntry(
                companyId: $run->company_id,
                label: "Paie {$run->period}",
                lines: $lines,
                sourceType: PayrollRun::class,
                sourceId: $run->id,
                user: $user,
            );

            return $run->fresh(['items.employee']);
        });
    }

    /**
     * Paiement des salaires : l'argent sort réellement (Débit Personnel dû /
     * Crédit Banque ou Caisse) et la dépense "paie" entre alors dans la
     * trésorerie et les indicateurs financiers.
     */
    public function pay(PayrollRun $run, string $method, User $user): PayrollRun
    {
        return DB::transaction(function () use ($run, $method, $user) {
            abort_if($run->status !== 'validee', 409, 'Seule une paie validée (et non déjà payée) peut être payée.');

            $net = round((float) $run->total_amount, 2);
            $treasury = in_array($method, ['virement', 'mobile_money', 'carte']) ? '512000' : '571000';

            $this->accountingService->createEntry(
                companyId: $run->company_id,
                label: "Paiement paie {$run->period}",
                lines: [
                    ['code' => '421000', 'debit' => $net, 'credit' => 0, 'label' => 'Règlement des salaires nets'],
                    ['code' => $treasury, 'debit' => 0, 'credit' => $net, 'label' => 'Sortie de trésorerie (paie)'],
                ],
                sourceType: PayrollRun::class,
                sourceId: $run->id,
                user: $user,
            );

            $run->update(['status' => 'payee', 'payment_date' => now()->toDateString()]);

            \App\Models\FinancialTransaction::create([
                'company_id' => $run->company_id,
                'type' => 'depense',
                'amount' => $net,
                'category' => 'paie',
                'reference_type' => PayrollRun::class,
                'reference_id' => $run->id,
                'transaction_date' => now()->toDateString(),
                'description' => "Paie {$run->period}",
            ]);

            return $run->fresh(['items.employee']);
        });
    }
}
