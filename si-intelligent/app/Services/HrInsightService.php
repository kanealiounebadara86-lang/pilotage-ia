<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class HrInsightService
{
    // Seuils d'alerte — volontairement simples et explicables (règles métier),
    // pas un modèle ML : suffisant pour repérer des signaux, et chaque seuil
    // est directement lisible dans le code (pas de boîte noire).
    private const LATENESS_RATE_ALERT = 0.20; // 20% de retards sur les jours travaillés
    private const OVERTIME_HOURS_ALERT = 20.0; // heures sup cumulées sur 30 jours
    private const ABSENCE_DAYS_ALERT = 3; // jours d'absence non couverts par un congé

    /**
     * Analyse un employé sur les 30 derniers jours et retourne des signaux
     * factuels avec leur justification — chaque alerte est explicable,
     * comme les autres modules IA du système (cohérence XAI).
     */
    public function analyzeEmployee(Employee $employee): array
    {
        $from = Carbon::now()->subDays(30)->toDateString();
        $to = Carbon::now()->toDateString();

        $records = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        $workedDays = $records->whereIn('status', ['present', 'retard'])->count();
        $lateDays = $records->where('status', 'retard')->count();
        $latenessRate = $workedDays > 0 ? round($lateDays / $workedDays, 2) : 0;
        $overtimeHours = round((float) $records->sum('overtime_hours'), 1);
        $leaveDays = $records->where('status', 'conge')->count();

        $signals = [];

        if ($latenessRate >= self::LATENESS_RATE_ALERT) {
            $signals[] = [
                'type' => 'assiduite',
                'severity' => 'moyenne',
                'label' => "Taux de retard élevé : {$lateDays} jour(s) sur {$workedDays} travaillés (".round($latenessRate * 100)."%)",
            ];
        }

        if ($overtimeHours >= self::OVERTIME_HOURS_ALERT) {
            $signals[] = [
                'type' => 'charge_travail',
                'severity' => 'moyenne',
                'label' => "Heures supplémentaires élevées sur 30 jours : {$overtimeHours}h — risque de surcharge ou de sous-effectif sur ce poste.",
            ];
        }

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName(),
            'period' => ['from' => $from, 'to' => $to],
            'worked_days' => $workedDays,
            'late_days' => $lateDays,
            'lateness_rate' => $latenessRate,
            'overtime_hours' => $overtimeHours,
            'leave_days' => $leaveDays,
            'signals' => $signals,
            'status' => empty($signals) ? 'rien_a_signaler' : 'a_surveiller',
        ];
    }
}
