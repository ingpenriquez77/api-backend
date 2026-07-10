<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Class AuditLog
 * * Modelo encargado de gestionar la persistencia de la Bitácora de Auditoría.
 */
class AuditLog extends Model
{
    // Especifica la conexión configurada para la base de datos.
    protected $connection = 'mongodb';
    // Define el nombre exacto de la colección en la BD
    protected $collection = 'bitacora_cambios';

    /**
     * Propiedad Guarded vacía para desproteger la asignación masiva.
     */
    protected $guarded = [];
}
