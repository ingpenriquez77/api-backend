<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * GET /api/productos
     * Listar todos los productos formateados de forma óptima para las tablas de Angular.
     * Mapeado automáticamente por el "index" de apiResource.
     */
    public function index(): JsonResponse
    {
        $productosRaw = Product::all();

        $productos = $productosRaw->map(function ($item) {
            return [
                'id' => $item->_id,
                'codigo_producto' => $item->codigo_producto,
                'nombre_producto' => $item->nombre_producto,
                'precio' => $item->precio,
                'marca' => $item->marca,
                'fecha_creacion' => Carbon::parse($item->created_at)->format('d/m/Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $productos
        ], 200);
    }

    /**
     * POST /api/productos
     * Crear un nuevo producto en la base NoSQL aplicando reglas estrictas de negocio.
     * Mapeado automáticamente por el "store" de apiResource.
     */
    public function store(Request $request): JsonResponse
    {
        // Validación estricta antes del almacenamiento NoSQL
        $validator = Validator::make($request->all(), [
            'nombre_producto' => 'required|string|max:255',
            'marca' => 'required|string|max:255',
            'precio' => 'required|numeric|min:1|max:999',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Casteo explícito a float para conservar la precisión del precio en MongoDB
        $producto = Product::create([
            'nombre_producto' => $request->nombre_producto,
            'marca' => $request->marca,
            'precio' => (float) $request->precio,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
            'data' => $producto
        ], 201);
    }

    /**
     * GET /api/productos/{id}
     * Obtener el detalle pormenorizado de un artículo a través de su ObjectId.
     * Mapeado automáticamente por el "show" de apiResource.
     */
    public function show($id): JsonResponse
    {
        $producto = Product::find($id);

        if (!$producto) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $producto->_id,
                'codigo_producto' => $producto->codigo_producto,
                'nombre_producto' => $producto->nombre_producto,
                'marca' => $producto->marca,
                'precio' => $producto->precio,
                'fecha_creacion' => Carbon::parse($producto->created_at)->format('d/m/Y H:i')
            ]
        ], 200);
    }

    /**
     * PUT /api/productos/{id}
     * Modificar datos de un artículo existente y disparar el registro de log de auditoría.
     * Mapeado automáticamente por el "update" de apiResource.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $producto = Product::find($id);

        if (!$producto) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
        }

        // Se usa 'sometimes' para soportar actualizaciones parciales desde el cliente
        $validator = Validator::make($request->all(), [
            'nombre_producto' => 'sometimes|required|string|max:255',
            'marca' => 'sometimes|required|string|max:255',
            'precio' => 'sometimes|required|numeric|min:1|max:999',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Snapshot histórico antes de aplicar la mutación en BD
        $valoresAnteriores = $producto->toArray();

        // Recuperamos el usuario inyectado previamente por tu TokenMongodbMiddleware
        $usuarioActivo = $request->attributes->get('usuario_autenticado');

        // Ejecutamos la actualización en la colección
        $producto->update($request->all());

        // Automatización de la Bitácora de Auditoría (Efecto Actualizar)
        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'actualizar_producto',
            'modelo_tipo' => 'Producto',
            'modelo_id' => $producto->_id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $producto->refresh()->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente',
            'data' => $producto
        ], 200);
    }

    /**
     * DELETE /api/productos/{id}
     * Eliminar físicamente un producto y respaldar su snapshot completo en los logs.
     * Mapeado automáticamente por el "destroy" de apiResource.
     */
    public function destroy($id): JsonResponse
    {
        $producto = Product::find($id);

        if (!$producto) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
        }

        // Accedemos a la petición global mediante el helper request() para capturar al operador activo
        $usuarioActivo = request()->attributes->get('usuario_autenticado');
        $valoresAnteriores = $producto->toArray();

        // Remoción del documento
        $producto->delete();

        // Automatización de la Bitácora de Auditoría (Efecto Eliminar)
        AuditLog::create([
            'usuario_id' => $usuarioActivo ? $usuarioActivo->_id : null,
            'usuario_nickname' => $usuarioActivo ? $usuarioActivo->usuario : 'sistema',
            'accion' => 'eliminar_producto',
            'modelo_tipo' => 'Producto',
            'modelo_id' => $id,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente'
        ], 200);
    }

    /**
     * GET /api/auditoria/productos
     * Ruta personalizada para aislar el log transaccional específico de este módulo.
     */
    public function listarAuditoriaProducto(): JsonResponse
    {
        // Filtramos de forma estricta los documentos cuyo 'modelo_tipo' coincida con este módulo
        $logs = AuditLog::where('modelo_tipo', 'Producto')
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
