<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdvanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Services\AdvanceService;
use Illuminate\Http\Request;

class EmployeeAdvanceController extends Controller
{
    public function __construct(private readonly AdvanceService $advanceService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $advances = EmployeeAdvance::where('company_id', $request->user()->company_id)
            ->with('employee')
            ->orderByDesc('granted_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json($advances);
    }

    public function store(StoreAdvanceRequest $request)
    {
        $employee = Employee::where('company_id', $request->user()->company_id)
            ->findOrFail($request->validated('employee_id'));

        abort_if(
            EmployeeAdvance::where('employee_id', $employee->id)->where('status', 'en_cours')->exists(),
            422,
            'Cet employé a déjà une avance en cours de remboursement.'
        );

        $advance = $this->advanceService->grantAdvance(
            $employee,
            $request->validated('amount'),
            $request->validated('monthly_installment'),
            $request->validated('reason'),
            $request->user(),
        );

        return response()->json($advance, 201);
    }
}
