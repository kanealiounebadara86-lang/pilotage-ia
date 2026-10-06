<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGatewayService
{
    /**
     * Pont unique entre Laravel et le service Python/FastAPI. Aucun autre
     * point du code Laravel ne doit appeler directement le service IA —
     * ça centralise l'authentification, le timeout et la gestion d'erreur
     * (cahier des charges section 29 : API Laravel ↔ Python).
     */
    private function client()
    {
        return Http::withHeaders(['X-API-Key' => config('services.ai.api_key')])
            ->baseUrl(config('services.ai.base_url'))
            ->timeout(60);
    }

    public function replenishment(int $productId): array
    {
        $response = $this->client()->post('/api/ai/replenishment', ['product_id' => $productId]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('Échec appel service IA (replenishment)', [
                'product_id' => $productId, 'status' => $response->status(), 'body' => $response->body(),
            ]);
            abort(502, "Le service d'optimisation IA n'a pas pu répondre.");
        }

        return $response->json();
    }

    /**
     * Transmet la question de l'utilisateur au service Python, avec SON
     * PROPRE token Sanctum (pas la clé de service) — c'est ce qui garantit
     * que l'assistant ne peut jamais accéder à plus de données que
     * l'utilisateur lui-même n'y aurait droit.
     */
    public function askAssistant(string $question, string $userToken): array
    {
        $response = $this->client()->timeout(120)->post('/api/ai/agent/query', [
            'question' => $question,
            'laravel_token' => $userToken,
        ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('Échec appel assistant IA', ['status' => $response->status(), 'body' => $response->body()]);
            abort(502, "L'assistant IA n'a pas pu répondre.");
        }

        return $response->json();
    }

    /** Phrase dictée -> intention structurée (le service Python n'exécute rien). */
    public function voiceIntent(string $transcript): array
    {
        $response = $this->client()->timeout(30)->post('/api/ai/voice/intent', ['transcript' => $transcript]);

        if ($response->failed()) {
            Log::error('Échec appel service IA (voice)', ['status' => $response->status(), 'body' => $response->body()]);

            return ['intent' => 'unknown', 'params' => [], 'message' => "Le service IA n'a pas répondu. Vérifiez qu'il est démarré (ai-service)."];
        }

        return $response->json();
    }

    public function forecast(int $productId, int $horizonDays = 14): array
    {
        $response = $this->client()->post('/api/ai/forecast', [
            'product_id' => $productId,
            'horizon_days' => $horizonDays,
        ]);

        if ($response->failed()) {
            Log::error('Échec appel service IA (forecast)', [
                'product_id' => $productId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            abort(502, "Le service de prévision IA n'a pas pu répondre. Vérifie qu'il est démarré (ai-service).");
        }

        return $response->json();
    }
}
