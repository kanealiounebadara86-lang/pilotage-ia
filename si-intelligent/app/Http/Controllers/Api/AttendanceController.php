<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Attendance as AttendanceModel;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    /**
     * Liste des pointages, filtrable par employé et par période (jour précis,
     * ou plage date_from/date_to — pratique pour "cette semaine"/"ce mois").
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $query = Attendance::where('company_id', $request->user()->company_id)->with('employee');

        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($date = $request->query('date')) {
            $query->where('date', $date);
        }
        if ($from = $request->query('date_from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('date', '<=', $to);
        }

        return response()->json(
            $query->orderByDesc('date')->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreAttendanceRequest $request)
    {
        $employee = Employee::where('company_id', $request->user()->company_id)
            ->findOrFail($request->validated('employee_id'));

        try {
            $this->attendanceService->assertCanBePresent($employee, $request->validated('date'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $attendance = $this->attendanceService->clockInOut(
            $employee,
            $request->validated('date'),
            $request->validated('clock_in'),
            $request->validated('clock_out'),
        );

        return response()->json($attendance->load('employee'), 201);
    }

    /**
     * Résumé des heures travaillées par employé sur une période — vue
     * pensée pour faciliter le travail RH (validation rapide des heures
     * avant la paie, sans éplucher chaque pointage individuellement).
     */
    public function summary(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $from = $request->query('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', Carbon::now()->toDateString());

        $rows = Attendance::where('company_id', $request->user()->company_id)
            ->whereBetween('date', [$from, $to])
            ->with('employee')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($records) {
                $employee = $records->first()->employee;

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->fullName(),
                    'days_present' => $records->whereIn('status', ['present', 'retard'])->count(),
                    'days_late' => $records->where('status', 'retard')->count(),
                    'days_leave' => $records->where('status', 'conge')->count(),
                    'total_hours' => round((float) $records->sum('hours_worked'), 2),
                    'overtime_hours' => round((float) $records->sum('overtime_hours'), 2),
                    'overtime_pending' => round((float) $records->filter(fn ($r) => $r->overtime_suggested > 0 && ! $r->overtime_validated_at)->sum('overtime_suggested'), 2),
                ];
            })
            ->values();

        return response()->json(['period' => ['from' => $from, 'to' => $to], 'employees' => $rows]);
    }

    /**
     * Tableau de pointage du jour : chaque employé actif avec son état
     * (non_pointe, present, parti, conge). Heure et date = celles du serveur.
     */
    public function today(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $companyId = $request->user()->company_id;
        $today = now()->toDateString();

        $records = Attendance::where('company_id', $companyId)->where('date', $today)->get()->keyBy('employee_id');

        $leaves = \App\Models\LeaveRequest::whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'approuvee')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->get()->keyBy('employee_id');

        $rows = Employee::where('company_id', $companyId)->where('status', 'actif')
            ->with('department')->orderBy('first_name')->get()
            ->map(function ($e) use ($records, $leaves) {
                $a = $records->get($e->id);
                $leave = $leaves->get($e->id);
                $state = 'non_pointe';
                if ($leave) {
                    $state = 'conge';
                } elseif ($a) {
                    $state = $a->status === 'conge' ? 'conge' : ($a->clock_out ? 'parti' : ($a->clock_in ? 'present' : 'non_pointe'));
                }

                return [
                    'employee_id' => $e->id,
                    'name' => $e->fullName(),
                    'position' => $e->position,
                    'department' => $e->department?->name,
                    'state' => $state,
                    'late' => $a?->status === 'retard',
                    'leave_until' => $leave ? \Illuminate\Support\Carbon::parse($leave->end_date)->format('d/m/Y') : null,
                    'leave_type' => $leave?->type,
                    'clock_in' => $a?->clock_in ? substr((string) $a->clock_in, 0, 5) : null,
                    'clock_out' => $a?->clock_out ? substr((string) $a->clock_out, 0, 5) : null,
                    'hours_worked' => $a ? (float) $a->hours_worked : 0,
                    'overtime_suggested' => $a ? (float) $a->overtime_suggested : 0,
                ];
            })->values();

        return response()->json([
            'date' => $today,
            'server_time' => now()->format('H:i:s'),
            'employees' => $rows,
        ]);
    }

    /** Pointer un employé (arrivée puis départ), heure du serveur. */
    public function punch(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        $data = $request->validate(['employee_id' => ['required', 'integer']]);

        $employee = Employee::where('company_id', $request->user()->company_id)->findOrFail($data['employee_id']);

        try {
            [$attendance, $action] = $this->attendanceService->punch($employee);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['action' => $action, 'attendance' => $attendance->load('employee')]);
    }

    /** Pointer l'arrivée de tous les employés actifs pas encore pointés (hors congés). */
    public function punchAll(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);

        $companyId = $request->user()->company_id;
        $today = now()->toDateString();
        $already = Attendance::where('company_id', $companyId)->where('date', $today)->pluck('employee_id');

        $count = 0;
        Employee::where('company_id', $companyId)->where('status', 'actif')
            ->whereNotIn('id', $already)->get()
            ->each(function ($e) use (&$count) {
                $this->attendanceService->punch($e);
                $count++;
            });

        return response()->json(['punched' => $count]);
    }

    /** La RH fixe/valide les heures supplémentaires d'un pointage (seules celles-ci comptent en paie). */
    public function setOvertime(Request $request, AttendanceModel $attendance)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        abort_unless($attendance->company_id === $request->user()->company_id, 404);

        $data = $request->validate(['overtime_hours' => ['required', 'numeric', 'min:0', 'max:16']]);

        return response()->json(
            $this->attendanceService->setOvertime($attendance, (float) $data['overtime_hours'])->load('employee')
        );
    }
}
