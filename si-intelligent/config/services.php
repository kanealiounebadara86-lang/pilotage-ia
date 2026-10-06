<?php

return [

    /*
     * Configuration du service IA Python/FastAPI (ai-service).
     * Les valeurs viennent du .env, jamais codées en dur ici.
     */
    'ai' => [
        'base_url' => env('AI_SERVICE_BASE_URL', 'http://localhost:8001'),
        'api_key' => env('AI_SERVICE_API_KEY'),
    ],

    'mail' => [
        'driver' => env('MAIL_MAILER', 'log'),
    ],

];
