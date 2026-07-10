<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Class Profile
 * * Representa la entidad de Perfil o Rol dentro de la base de datos.
 * Define los permisos globales del sistema mapeando las vistas autorizadas para el Frontend.
*/
class Profile extends Model
{
    // Conexión específica configurada para el motor de base de datos.
    protected $connection = 'mongodb';
    // Nombre exacto de la colección dentro de MongoDB donde se alojan estos documentos
    protected $collection = 'profiles';

    // Atributos habilitados para asignación masiva desde los formularios y peticiones de la API
    protected $fillable = [
        'codigo_perfil',
        'nombre_perfil',
        'secciones_permitidas'
    ];

    /**
     * Intercepta los eventos nativos de Eloquent para automatizar reglas de negocio.
     */
    protected static function boot()
    {
        parent::boot();

        // Se ejecuta justo antes de insertar un nuevo documento de perfil en BD.
        static::creating(function ($perfil) {

            // Genera de forma automatizada un identificador único con el prefijo institucional "PERF-".
            $perfil->codigo_perfil = 'PERF-' . strtoupper(substr(uniqid(), 7, 6));
        });
    }

    /**
     * Relación Inversa Muchos a Muchos 
     * * Permite consultar de forma fluida qué usuarios tienen asignado este perfil en particular.
     * Datos directamente entre los documentos vinculados, eliminando por completo las tablas de unión relacionales.
     */
    public function usuarios()
    {
        return $this->belongsToMany(User::class, null, 'perfil_ids', 'usuario_ids');
    }
}
