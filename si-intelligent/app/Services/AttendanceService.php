<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;

class AttendanceService
{
    private const STANDARD_HOURS_PER_DAY = 8.0;
    private const STANDARD_START_TIME = '08:00:00';

    /**
     * Enregistre un pointage (entrée/sortie), calcule les heures travaillées et
     * le statut (retard si l'entrée dépasse l'heure standard).
     *
     * Les heures supplémentaires (au-delà de 8h/jour) ne sont que SUGGÉRÉES :
     * c'est la RH qui les valide (setOvertime). Seules les heures validées
     * (overtime_hours) sont prises en compte dans la paie.
     * $validateOvertime = true valide directement la suggestion (jeu de démo).
     */
    public function clockInOut(Employee $employee, string $date, ?string $clockIn, ?string $clockOut, bool $validateOvertime = false): Attendance
    {
        $hoursWorked = 0;
        $suggested = 0;
        $status = 'present';

        if ($clockIn && $clockOut) {
            $in = Carbon::parse($clockIn);
            $out = Carbon::parse($clockOut);
            $hoursWorked = max(0, round($in->diffInMinutes($out, false) / 60, 2));
            $suggested = max(0, round($hoursWorked - self::STANDARD_HOURS_PER_DAY, 2));
        }

        if ($clockIn && Carbon::parse($clockIn)->gt(Carbon::parse(self::STANDARD_START_TIME))) {
            $status = 'retard';
        }

        $existing = Attendance::where('employee_id', $employee->id)->where('date', $date)->first();

        $attributes = [
            'company_id' => $employee->company_id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'hours_worked' => $hoursWorked,
            'overtime_suggested' => $suggested,
            'status' => $status,
        ];

        if ($validateOvertime) {
            $attributes['overtime_hours'] = $suggested;
            $attributes['overtime_validated_at'] = $suggested > 0 ? now() : null;
        } elseif ($existing && $existing->overtime_validated_at) {
            // Une validation RH existante ne dépasse jamais la nouvelle suggestion.
            $attributes['overtime_hours'] = min((float) $existing->overtime_hours, $suggested);
        } else {
            $attributes['overtime_hours'] = 0;
        }

        return Attendance::updateOrCreate(['employee_id' => $employee->id, 'date' => $date], $attributes);
    }

    /**
     * Pointage "un clic" : la 1re fois de la journée enregistre l'arrivée,
     * la 2e le départ. Date et heure viennent du SERVEUR (pas du navigateur).
     * Retourne [Attendance, 'arrivee'|'depart'] ou lève une exception métier.
     */
    public function punch(Employee $employee): array
    {
        $today = now()->toDateString();
        $time = now()->format('H:i');
        $record = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();

        $this->assertCanBePresent($employee, $today);

        if ($record && $record->status === 'conge') {
            throw new \DomainException('Cet employé est en congé aujourd\'hui.');
        }

        if (! $record || ! $record->clock_in) {
            return [$this->clockInOut($employee, $today, $time, null), 'arrivee'];
        }

        if (! $record->clock_out) {
            $in = substr((string) $record->clock_in, 0, 5);

            return [$this->clockInOut($employee, $today, $in, $time), 'depart'];
        }

        throw new \DomainException('Arrivée et départ déjà pointés aujourd\'hui.');
    }

    /** Congé approuvé couvrant cette date, s'il existe. */
    public function leaveOn(Employee $employee, string $date): ?\App\Models\LeaveRequest
    {
        return \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approuvee')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /** Un employé en congé (ou inactif) ne peut pas être pointé présent. */
    public function assertCanBePresent(Employee $employee, string $date): void
    {
        if ($employee->status !== 'actif') {
            throw new \DomainException('Cet employé n\'est pas actif.');
        }
        if ($leave = $this->leaveOn($employee, $date)) {
            throw new \DomainException('Cet employé est en congé du '.$leave->start_date->format('d/m/Y').' au '.$leave->end_date->format('d/m/Y').' : il ne peut pas être pointé présent.');
        }
    }

    /** Heures sup validées (ou corrigées) par la RH. */
    public function setOvertime(Attendance $attendance, float $hours): Attendance
    {
        $attendance->update([
            'overtime_hours' => $hours,
            'overtime_validated_at' => now(),
        ]);

        return $attendance->fresh();
    }

    /**
     * Génère des présences "congé" pour chaque jour de la période — appelé
     * automatiquement à l'approbation d'un congé, pour que le calendrier de
     * présence reflète l'absence sans saisie manuelle en double.
     */
    public function markLeavePeriod(Employee $employee, Carbon $start, Carbon $end, string $leaveTypeLabel = 'Congé'): void
    {
        $current = $start->copy();

        while ($current->lte($end)) {
            Attendance::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $current->toDateString()],
                ['company_id' => $employee->company_id, 'status' => 'conge', 'clock_in' => null, 'clock_out' => null, 'hours_worked' => 0, 'overtime_hours' => 0, 'overtime_suggested' => 0, 'overtime_validated_at' => null, 'note' => $leaveTypeLabel]
            );
            $current->addDay();
        }
    }

    /**
     * Résumé du mois pour le calcul de paie : total des heures sup et nombre
     * de jours d'absence non rémunérée sur la période.
     */
    public function monthlySummary(Employee $employee, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        return [
            'overtime_hours' => (float) $records->sum('overtime_hours'),
            'lateness_count' => $records->where('status', 'retard')->count(),
            'leave_days' => $records->where('status', 'conge')->count(),
        ];
    }
}
