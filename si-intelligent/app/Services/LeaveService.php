<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function requestLeave(array $data): LeaveRequest
    {
        $employee = \App\Models\Employee::findOrFail($data['employee_id']);
        $duration = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        if ($data['type'] === 'conges_payes') {
            abort_if(
                $duration > $employee->leave_balance_days,
                422,
                "Solde de congés insuffisant : {$employee->leave_balance_days} jour(s) disponible(s), {$duration} demandé(s)."
            );
        }

        $this->assertNoOverlap($employee->id, $data['start_date'], $data['end_date']);

        return LeaveRequest::create([...$data, 'status' => 'demandee']);
    }

    /**
     * Approuve un congé : décrémente le solde (congés payés uniquement — un
     * congé sans solde ou maladie n'entame pas le solde), et fait apparaître
     * l'absence dans le calendrier de présence (module RH).
     */
    public function review(LeaveRequest $leave, string $status, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $status, $reviewer) {
            abort_if($leave->status !== 'demandee', 409, 'Cette demande a déjà été traitée.');

            if ($status === 'approuvee') {
                $employee = $leave->employee;
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);

                // Cohérence avec le pointage : on n'approuve pas un congé sur des jours
                // où l'employé a déjà été pointé présent, et jamais deux congés qui se chevauchent.
                $this->assertNoOverlap($employee->id, $start->toDateString(), $end->toDateString(), $leave->id, true);
                $worked = \App\Models\Attendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->whereIn('status', ['present', 'retard'])->whereNotNull('clock_in')->orderBy('date')->first();
                if ($worked) {
                    abort(422, 'Impossible : cet employé est déjà pointé présent le '.Carbon::parse($worked->date)->format('d/m/Y').'. Corrigez le pointage avant d\'approuver le congé.');
                }
            }

            $leave->update(['status' => $status, 'reviewed_by' => $reviewer->id]);

            if ($status === 'approuvee') {
                if ($leave->type === 'conges_payes') {
                    $employee->decrement('leave_balance_days', $leave->durationInDays());
                }

                $leaveLabels = [
                    'conges_payes' => 'Congés payés', 'maladie' => 'Maladie',
                    'sans_solde' => 'Congé sans solde', 'autre' => 'Congé (autre)',
                ];
                $this->attendanceService->markLeavePeriod($employee, $start, $end, $leaveLabels[$leave->type] ?? 'Congé');
            }

            return $leave->fresh(['employee']);
        });
    }

    private function assertNoOverlap(int $employeeId, string $start, string $end, ?int $ignoreId = null, bool $approvedOnly = false): void
    {
        $query = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', $approvedOnly ? ['approuvee'] : ['approuvee', 'demandee'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        abort_if($query->exists(), 422, 'Cet employé a déjà un congé (demandé ou approuvé) qui chevauche ces dates.');
    }
}
