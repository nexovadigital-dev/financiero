<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleSessionExpired
{
    /**
     * Handle an incoming request.
     * Si la sesión ha expirado, mostrar mensaje amigable en vez de error 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Si es una solicitud AJAX/Livewire y el usuario no está autenticado
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                // Para solicitudes Livewire, devolver respuesta que recarga la página
                return response()->json([
                    'message' => 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.',
                    'redirect' => route('filament.admin.auth.login'),
                    'session_expired' => true,
                ], 419); // 419 = Session Expired (Laravel standard)
            }
        }

        return $response;
    }
}
