<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class AutenticacionController extends Controller
{
    /**
     * POST /api/login
     * * Procesa la autenticación del usuario mediante credenciales, genera un token.
     */
    public function login(Request $request): JsonResponse
    {
        // Validación de campos obligatorios
        $validator = Validator::make($request->all(), [
            'correo_electronico' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Búsqueda del usuario en la colección NoSQL
        $usuario = User::where('correo_electronico', $request->correo_electronico)->first();

        // Verificación de existencia y coincidencia del hash Bcrypt de la contraseña
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales introducidas son incorrectas.'
            ], 411);
        }

        // Mecanismo de Sesión Nativo MongoDB .
        $token = Str::random(60);

        // Almacenamos el token en SHA-256 en la base de datos por seguridad
        $usuario->update([
            'api_token' => hash('sha256', $token)
        ]);

        // Respuesta estructurada para consumo inmediato en Angular
        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'usuario' => [
                'id' => $usuario->_id,
                'nombre_completo' => $usuario->nombre_completo,
                'usuario' => $usuario->usuario,
                'perfil_ids' => $usuario->perfil_ids ?? []
            ]
        ], 200);
    }

    /**
     * POST /api/logout
     * * Revoca los accesos del usuario actual destruyendo el token activo.
     */
    public function logout(Request $request): JsonResponse
    {
        $usuario = $request->attributes->get('usuario_autenticado');

        if ($usuario) {
            // Invalidamos el token en la base de datos volviéndolo nulo
            $usuario->update([
                'api_token' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente y token destruido.'
        ], 200);
    }

    public function recuperarPassword(Request $request)
    {
        // 1. Validar correo electrónico
        $validator = Validator::make($request->all(), [
            'correo_electronico' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Por favor, ingresa un correo electrónico válido.'
            ], 400);
        }

        // 2. Verificar si el usuario existe en MongoDB
        $usuario = User::where('correo_electronico', $request->correo_electronico)->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico no coincide con ningún usuario registrado.'
            ], 404);
        }

        // 3. Generar una nueva contraseña temporal limpia (ej: TEMP8A3F)
        $nuevaContrasena = 'TEMP' . strtoupper(Str::random(4));

        try {
            // 4. Forzamos a MongoDB a guardar el texto plano saltándose cualquier mutador hash (SHA/Bcrypt)
            $usuario->update([
                'password' => $nuevaContrasena
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la credencial en la base de datos NoSQL: ' . $e->getMessage()
            ], 500);
        }

        // 5. DISPARAR EL WEBHOOK HACIA MAKE.COM
        $webhookUrl = 'https://hook.us2.make.com/h5nmyatfga4grvn34mutbgkzw5mt6vsf';

        try {
            $response = Http::post($webhookUrl, [
                'correo' => $usuario->correo_electronico,
                'usuario_nickname' => $usuario->usuario ?? 'Usuario',
                'nueva_contrasena' => $nuevaContrasena,
                'fecha' => now()->format('d/m/Y H:i:s')
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario existe y se actualizó, pero Make.com rechazó la petición.'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con el Webhook de Make.com: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '¡Éxito! Tu contraseña ha sido reestablecida y enviada a tu correo electrónico vía Make.com.'
        ], 200);
    }
}
