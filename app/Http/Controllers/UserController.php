<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * GET /api/usuarios
     * Listar todos los usuarios formateando las dependencias.
     */
    public function listar(): JsonResponse
    {
        $usuariosRaw = User::all();

        $usuarios = $usuariosRaw->map(function ($item) {
            return [
                'id' => $item->_id,
                'codigo_usuario' => $item->codigo_usuario,
                'usuario' => $item->usuario,
                'nombre_completo' => $item->nombre_completo,
                'correo_electronico' => $item->correo_electronico,
                'telefono' => $item->telefono,
                'foto_perfil' => $item->foto_perfil ? asset('storage/' . $item->foto_perfil) : null,
                'perfiles' => $item->perfiles()->get(['_id', 'nombre_perfil']),
                'fecha_creacion' => Carbon::parse($item->created_at)->format('d/m/Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $usuarios
        ], 200);
    }

    /**
     * POST /api/usuarios
     * Crear un nuevo usuario en la colección NoSQL y guardar la imagen físicamente.
     */
    public function guardar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'usuario' => 'required|string|max:255|unique:users,usuario',
            'nombre_completo' => 'required|string|max:255',
            'correo_electronico' => 'required|email|unique:users,correo_electronico',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string',
            'perfil_ids' => 'required|array',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:20480'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $rutaFoto = null;
        if ($request->hasFile('foto_perfil')) {
            $rutaFoto = $request->file('foto_perfil')->store('fotos_usuarios', 'public');
        }

        $usuario = User::create([
            'usuario' => $request->usuario,
            'nombre_completo' => $request->nombre_completo,
            'correo_electronico' => $request->correo_electronico,
            'password' => Hash::make($request->password),
            'telefono' => $request->telefono,
            'foto_perfil' => $rutaFoto,
            'perfil_ids' => $request->perfil_ids
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'data' => $usuario
        ], 201);
    }

    /**
     * GET /api/usuarios/{id}
     * Mostrar información detallada de un usuario por su ObjectId de BD.
     */
    public function mostrar($id): JsonResponse
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $usuario->_id,
                'codigo_usuario' => $usuario->codigo_usuario,
                'usuario' => $usuario->usuario,
                'nombre_completo' => $usuario->nombre_completo,
                'correo_electronico' => $usuario->correo_electronico,
                'telefono' => $usuario->telefono,
                'foto_perfil' => $usuario->foto_perfil ? asset('storage/' . $usuario->foto_perfil) : null,
                'perfil_ids' => $usuario->perfil_ids ?? [],
                'fecha_creacion' => Carbon::parse($usuario->created_at)->format('d/m/Y H:i')
            ]
        ], 200);
    }

    /**
     * POST /api/usuarios/{id}
     * Actualizar los metadatos del usuario e interactuar con la Bitácora de Auditoría.
     */
    public function actualizar(Request $request, $id): JsonResponse
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        // Snapshot completo del documento antes de aplicar cambios
        $valoresAnteriores = $usuario->toArray();

        // Extraemos al usuario inyectado por TokenMongodbMiddleware
        $usuarioActivo = $request->attributes->get('usuario_autenticado');

        // Filtramos los datos para evitar meter basura de FormData a MongoDB
        $datos = $request->except(['_method', 'foto_perfil']);

        if (!empty($request->password)) {
            $datos['password'] = Hash::make($request->password);
        } else {
            unset($datos['password']);
        }

        // Manejo de la foto de perfil si subieron un archivo nuevo
        if ($request->hasFile('foto_perfil')) {
            $file = $request->file('foto_perfil');
            $path = $file->store('avatars', 'public');
            $datos['foto_perfil'] = asset('storage/' . $path);
        }

        $usuario->update($datos);

        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'ACTUALIZAR USUARIO',
            'modelo_tipo' => 'Usuario',
            'modelo_id' => $usuario->_id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $usuario->refresh()->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente',
            'data' => $usuario
        ], 200);
    }

    /**
     * DELETE /api/usuarios/{id}
     * Eliminar físicamente un registro de usuario y auditar la pérdida de datos.
     */
    public function eliminar(Request $request, $id): JsonResponse
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        //  Snapshot antes de destruir la información
        $usuarioActivo = $request->attributes->get('usuario_autenticado');
        $valoresAnteriores = $usuario->toArray();

        // Remoción física del documento en BD
        $usuario->delete();

        // Crea los datos en la tabla bitacora_cambios - Eliminar
        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'eliminar_usuario',
            'modelo_tipo' => 'Usuario',
            'modelo_id' => $id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente'
        ], 200);
    }

    /**
     * GET /api/auditoria/usuarios
     * Recupera el historial de auditoría enfocado únicamente en los cambios de usuarios.
     */
    public function listarAuditoriaUsuario(): JsonResponse
    {
        // Buscamos solo donde 'modelo_tipo' sea exactamente 'Usuario'
        $logs = AuditLog::where('modelo_tipo', 'Usuario')
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->_id,
                    'usuario' => $log->usuario_nickname,
                    'accion' => str_replace('_', ' ', strtoupper($log->accion)),
                    'registro_afectado_id' => $log->modelo_id,
                    'valores_anteriores' => $log->valores_anteriores,
                    'valores_nuevos' => $log->valores_nuevos,
                    'fecha_movimiento' => Carbon::parse($log->created_at)->format('d/m/Y H:i:s')
                ];
            })
        ], 200);
    }
}
