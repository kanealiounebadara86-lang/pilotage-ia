<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Consultation de la piste d'audit — répond à "pourquoi cette donnée
     * a-t-elle changé ?" (module 12). Filtrable par type de modèle et par
     * identifiant, pour retrouver l'historique d'un enregistrement précis.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('admin.manage_users'), 403);

        $query = AuditLog::where('company_id', $request->user()->company_id)->with('user');

        if ($subjectType = $request->query('subject_type')) {
            $query->where('subject_type', 'App\\Models\\'.$subjectType);
        }
        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 30))
        );
    }
}
