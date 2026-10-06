<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\HrInsightService;
use Illuminate\Http\Request;

class HrInsightController extends Controller
{
    public function __construct(private readonly HrInsightService $hrInsightService)
    {
    }

    /**
     * Lance l'IA RH sur un périmètre : "scope: all" (tous les employés actifs)
     * ou une liste "employee_ids".
     */
    public function run(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $companyId = $request->user()->company_id;

        $employees = $request->input('scope') === 'all'
            ? Employee::where('company_id', $companyId)->where('status', 'actif')->get()
            : Employee::where('company_id', $companyId)->whereIn('id', $request->input('employee_ids', []))->get();

        $results = $employees->map(fn ($e) => $this->hrInsightService->analyzeEmployee($e))->values();

        return response()->json([
            'count' => $results->count(),
            'flagged_count' => $results->where('status', 'a_surveiller')->count(),
            'results' => $results,
        ]);
    }
}
