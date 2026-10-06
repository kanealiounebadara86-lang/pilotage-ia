<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Derrière un tunnel HTTPS (accès depuis le téléphone), les liens générés
        // doivent rester en https, sinon le micro et l'installation de l'application échouent.
        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
