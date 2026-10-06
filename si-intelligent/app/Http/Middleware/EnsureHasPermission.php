<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Vérifie que l'utilisateur authentifié possède la permission requise.
     * Usage dans les routes : ->middleware('permission:sales.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, "Accès refusé : permission '{$permission}' requise.");
        }

        return $next($request);
    }
}
