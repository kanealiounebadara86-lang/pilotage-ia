<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\VoiceCommandException;
use App\Http\Controllers\Controller;
use App\Services\VoiceCommandService;
use Illuminate\Http\Request;

class VoiceCommandController extends Controller
{
    public function __construct(private readonly VoiceCommandService $voice)
    {
    }

    /** Étape 1 : comprendre la phrase et préparer le plan (rien n'est exécuté ici). */
    public function interpret(Request $request)
    {
        $data = $request->validate(['transcript' => ['required', 'string', 'max:500']]);

        return response()->json($this->voice->interpret($data['transcript'], $request->user()));
    }

    /** Étape 2 : exécuter le plan (après confirmation si l'action est sensible). */
    public function execute(Request $request)
    {
        $data = $request->validate(['intent' => ['required', 'string'], 'payload' => ['nullable', 'array']]);

        try {
            return response()->json($this->voice->execute($data['intent'], $data['payload'] ?? [], $request->user()));
        } catch (VoiceCommandException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
