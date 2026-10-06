<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $employees = Employee::where('company_id', $request->user()->company_id)
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 30));

        return response()->json($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = Employee::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'status' => 'actif',
        ]);

        return response()->json($employee, 201);
    }

    public function show(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);
        abort_unless($employee->company_id === $request->user()->company_id, 403);

        return response()->json($employee->load(['contracts', 'leaveRequests']));
    }

    public function update(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        abort_unless($employee->company_id === $request->user()->company_id, 403);

        $validated = $request->validate([
            'position' => ['sometimes', 'string', 'max:100'],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:actif,suspendu,sorti'],
        ]);

        $employee->update($validated);

        return response()->json($employee->fresh());
    }
}
