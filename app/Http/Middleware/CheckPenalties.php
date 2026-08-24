<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Penalty;

class CheckPenalties
{
    public function handle(Request $request, Closure $next): Response
    {
        // El bloqueo se maneja de forma contextual e interactiva en la UI (modales y banners)
        // manteniendo la ruta de liquidación disponible si se desea acceso directo.
        return $next($request);
    }
}
