<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Liste des alertes intelligentes actives (module 24), triées par sévérité.
     */
    public function index(Request $request)
    {
        $query = Alert::where('company_id', $request->user()->company_id);

        if (! $request->boolean('include_resolved')) {
            $query->where('is_resolved', false);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        $severityOrder = "FIELD(severity, 'critique','elevee','moyenne','faible')";

        return response()->json(
            $query->orderByRaw($severityOrder)->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 30))
        );
    }

    public function resolve(Request $request, Alert $alert)
    {
        abort_unless($alert->company_id === $request->user()->company_id, 403);

        $alert->update(['is_resolved' => true, 'resolved_at' => now()]);

        return response()->json($alert->fresh());
    }
}
