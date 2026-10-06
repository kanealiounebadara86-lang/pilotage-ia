<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiGatewayService;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private readonly AiGatewayService $aiGateway)
    {
    }

    /**
     * Assistant conversationnel (module 21, phase 8). Le token de la requête
     * courante est transmis tel quel au service IA, qui l'utilisera pour
     * chaque appel d'outil — l'assistant hérite donc exactement des
     * permissions du rôle de l'utilisateur, sans rien de plus.
     */
    public function ask(Request $request)
    {
        abort_unless($request->user()->hasPermission('ai.use_assistant'), 403);

        $validated = $request->validate(['question' => ['required', 'string', 'max:1000']]);

        $token = $request->bearerToken();
        abort_unless($token, 401, 'Token manquant.');

        $result = $this->aiGateway->askAssistant($validated['question'], $token);

        return response()->json($result);
    }
}
