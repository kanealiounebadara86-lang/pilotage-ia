<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $runs = PayrollRun::where('company_id', $request->user()->company_id)
            ->orderByDesc('period')
            ->paginate($request->integer('per_page', 20));

        return response()->json($runs);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);

        $validated = $request->validate(['period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']]);

        $run = $this->payrollService->generateDraft($request->user()->company_id, $validated['period'], $request->user());

        return response()->json($run, 201);
    }

    public function show(Request $request, PayrollRun $payrollRun)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);
        abort_unless($payrollRun->company_id === $request->user()->company_id, 403);

        return response()->json($payrollRun->load('items.employee.department'));
    }

    /**
     * Supprime un brouillon de paie (pour le régénérer après avoir ajouté
     * du pointage ou des congés qui n'étaient pas encore saisis). Une paie
     * déjà validée ne peut jamais être supprimée (intégrité comptable).
     */
    public function destroy(Request $request, PayrollRun $payrollRun)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        abort_unless($payrollRun->company_id === $request->user()->company_id, 403);
        abort_if($payrollRun->status !== 'brouillon', 409, 'Seul un brouillon peut être supprimé — une paie validée est définitive.');

        $payrollRun->items()->delete();
        $payrollRun->delete();

        return response()->json(['message' => 'Brouillon de paie supprimé.']);
    }

    public function validatePayroll(Request $request, PayrollRun $payrollRun)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        abort_unless($payrollRun->company_id === $request->user()->company_id, 403);

        $run = $this->payrollService->validate($payrollRun, $request->user());

        return response()->json($run);
    }

    public function payPayroll(Request $request, PayrollRun $payrollRun)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);
        abort_unless($payrollRun->company_id === $request->user()->company_id, 403);

        $data = $request->validate(['method' => ['nullable', 'in:especes,mobile_money,virement,cheque,carte']]);

        return response()->json($this->payrollService->pay($payrollRun, $data['method'] ?? 'especes', $request->user()));
    }
}
