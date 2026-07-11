<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Profile;

class ValidarSeccionMiddleware
{
    public function handle(Request $request, Closure $next, string $seccion): Response
    {
        // 1. Extraemos al usuario inyectado previamente por tu TokenMongodbMiddleware
        $usuarioActivo = $request->attributes->get('usuario_autenticado');

        if (!$usuarioActivo) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        // Buscamos el perfil del usuario en BD usando su primer perfil_ids
        $perfilId = is_array($usuarioActivo->perfil_ids) ? ($usuarioActivo->perfil_ids[0] ?? null) : $usuarioActivo->perfil_ids;

        $perfil = Profile::find($perfilId);

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'El usuario no cuenta con un rol válido asignado'], 403);
        }

        // Validamos si la sección requerida en la ruta está permitida para este rol
        if (!in_array($seccion, $perfil->secciones_permitidas)) {
            return response()->json([
                'success' => false,
                'message' => "Acceso denegado. Tu perfil no tiene autorización para la sección: {$seccion}"
            ], 403);
        }

        return $next($request);
    }
}
