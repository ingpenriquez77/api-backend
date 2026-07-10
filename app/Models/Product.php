<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Class Product
 * * Representa la entidad de un Producto dentro de la base de datos.
 */
class Product extends Model
{
    // Conexión específica hacia el motor de base de datos
    protected $connection = 'mongodb';
    // Nombre de la colección dentro de MongoDB
    protected $collection = 'products';

    // Atributos habilitados para asignación masiva desde los formularios y peticiones API
    protected $fillable = [
        'codigo_producto',
        'nombre_producto',
        'marca',
        'precio'
    ];

    /**
     * Ciclo de vida del Modelo (Booting).
     * Intercepta los eventos nativos de Eloquent para automatizar reglas de negocio.
     */
    protected static function boot()
    {
        parent::boot();

        // Se ejecuta justo antes de insertar un nuevo documento en MongoDB.
        static::creating(function ($product) {

            // Genera un identificador único alfanumérico basado en microsegundos (uniqid)
            // lo convierte a mayúsculas y le añade el prefijo corporativo "PROD-".
            // Esto garantiza códigos únicos sin requerir entrada manual del usuario.
            $product->codigo_producto = 'PROD-' . strtoupper(substr(uniqid(), 7, 6));
        });
    }
}
