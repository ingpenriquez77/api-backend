<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * GET /api/perfiles
     * Listar todos los perfiles/roles con su mapeo de permisos para Angular.
     * Mapeado automáticamente por el "index" de apiResource.
     */
    public function index(): JsonResponse
    {
        $perfilesRaw = Profile::all();

        $perfiles = $perfilesRaw->map(function ($item) {
            return [
                'id' => $item->_id,
                'codigo_perfil' => $item->codigo_perfil,
                'nombre_perfil' => $item->nombre_perfil,
                'secciones_permitidas' => $item->secciones_permitidas ?? [],
                'fecha_creacion' => Carbon::parse($item->created_at)->format('d/m/Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $perfiles
        ], 200);
    }

    /**
     * POST /api/perfiles
     * Crear un nuevo perfil de acceso en el sistema NoSQL.
     * Mapeado automáticamente por el "store" de apiResource.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_perfil' => 'required|string|unique:profiles,nombre_perfil|max:255',
            'secciones_permitidas' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $perfil = Profile::create([
            'nombre_perfil' => $request->nombre_perfil,
            'secciones_permitidas' => $request->secciones_permitidas,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil creado correctamente',
            'data' => $perfil
        ], 201);
    }

    /**
     * GET /api/perfiles/{id}
     * Detalle específico de un perfil de usuario utilizando su ObjectId.
     * Mapeado automáticamente por el "show" de apiResource.
     */
    public function show($id): JsonResponse
    {
        $perfil = Profile::find($id);

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $perfil->_id,
                'codigo_perfil' => $perfil->codigo_perfil,
                'nombre_perfil' => $perfil->nombre_perfil,
                'secciones_permitidas' => $perfil->secciones_permitidas ?? [],
                'fecha_creacion' => Carbon::parse($perfil->created_at)->format('d/m/Y H:i')
            ]
        ], 200);
    }

    /**
     * PUT /api/perfiles/{id}
     * Actualizar los alcances de un perfil y auditar sus cambios de permisos.
     * Mapeado automáticamente por el "update" de apiResource.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $perfil = Profile::find($id);

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_perfil' => 'sometimes|required|string|max:255|unique:profiles,nombre_perfil,' . $id . ',_id',
            'secciones_permitidas' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $valoresAnteriores = $perfil->toArray();
        $usuarioActivo = $request->attributes->get('usuario_autenticado');

        $perfil->update($request->all());

        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'actualizar_perfil',
            'modelo_tipo' => 'Perfil',
            'modelo_id' => $perfil->_id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $perfil->refresh()->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => $perfil
        ], 200);
    }

    /**
     * DELETE /api/perfiles/{id}
     * Remover un perfil de acceso y registrar el rastro completo en la bitácora.
     * Mapeado automáticamente por el "destroy" de apiResource.
     */
    public function destroy($id): JsonResponse
    {
        $perfil = Profile::find($id);

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        // Accedemos a la petición global mediante el helper request() para atrapar el snapshot del operador
        $usuarioActivo = request()->attributes->get('usuario_autenticado');
        $valoresAnteriores = $perfil->toArray();

        $perfil->delete();

        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'eliminar_perfil',
            'modelo_tipo' => 'Perfil',
            'modelo_id' => $id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente'
        ], 200);
    }

    /**
     * GET /api/auditoria/perfiles
     * Recupera el historial de auditoría enfocado únicamente en los cambios de perfiles.
     */
    public function listarAuditoriaPerfil(): JsonResponse
    {
        $logs = AuditLog::where('modelo_tipo', 'Perfil')
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
