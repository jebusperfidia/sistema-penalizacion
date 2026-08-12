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
        // Excluimos explícitamente la ruta de liquidación para evitar un bucle de redirecciones infinitas
        if ($request->routeIs('penalties.liquidation')) {
            return $next($request);
        }

        $hasUnpaidPenalty = Penalty::where('estado_pago', false)->exists();

        if ($hasUnpaidPenalty) {
            return redirect()->route('penalties.liquidation');
        }

        return $next($request);
    }
}
