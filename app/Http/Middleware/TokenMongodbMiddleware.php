<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class TokenMongodbMiddleware
{
    /**
     * Intercepta las peticiones entrantes, extrae y valida el token Bearer
     * contra la colección de usuarios en MongoDB usando cifrado SHA-256.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extraer la cabecera de autorización "Authorization: Bearer <token>"
        $tokenConBearer = $request->header('Authorization');

        // Limpiamos el prefijo 'Bearer ' para aislar la cadena pura del token
        $token = str_replace('Bearer ', '', $tokenConBearer);

        // Control de seguridad temprana: Validar presencia del token
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Falta el Token de acceso.'
            ], 401);
        }

        // Consulta indexada utilizando la firma SHA-256 del token original
        $usuario = User::where('api_token', hash('sha256', $token))->first();

        // Control de seguridad: Verificar validez del token en MongoDB
        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Token inválido, revocado o expirado.'
            ], 401);
        }

        // Inyección de Contexto
        // Guardamos el objeto completo del usuario autenticado en los atributos del request
        // permitiendo que los Controladores y la Bitácora de Auditoría lo consuman de inmediato.
        $request->attributes->set('usuario_autenticado', $usuario);

        // Permitimos que la petición continúe protegida
        return $next($request);
    }
}
