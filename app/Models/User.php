<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
// Heredamos de la clase Authenticatable de MongoDB para habilitar los sistemas de seguridad y login nativos
use MongoDB\Laravel\Auth\User as Authenticatable;

/**
 * Class User
 * * Representa la entidad de Usuario dentro de la base de datos.
 * Gestiona las credenciales de acceso, la asociación de perfiles y el token manual de sesión.
 */
class User extends Authenticatable
{
    use Notifiable;

    // Conexión dedicada hacia el motor de base de datos.
    protected $connection = 'mongodb';
    // Nombre de la colección dentro de BD donde residen estos documentos
    protected $collection = 'users';

    // Atributos habilitados para asignación masiva desde los formularios y peticiones de la API
    protected $fillable = [
        'codigo_usuario',
        'usuario',
        'nombre_completo',
        'correo_electronico',
        'password',
        'telefono',
        'foto_perfil',
        'perfil_ids',
        'api_token'
    ];

    // Atributos que se ocultan automáticamente cuando el modelo se convierte a arreglos o JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function boot()
    {
        parent::boot();


        // Se ejecuta justo antes de insertar un nuevo documento de usuario en BD.
        static::creating(function ($usuario) {
            // Genera un código único con el prefijo "USU-" de manera automatizada.
            $usuario->codigo_usuario = 'USU-' . strtoupper(substr(uniqid(), 7, 6));
        });
    }

    /**
     * Relación Muchos a Muchos NoSQL (BelongsToMany)
     * * Conecta de forma fluida la colección de usuarios con la colección de perfiles/roles.
     * Al ser MongoDB, mapea de forma nativa los ObjectIds almacenados dentro del arreglo 'perfil_ids'
     * evitando la necesidad de crear una tabla pivote intermedia relacional.
     */
    public function perfiles()
    {
        return $this->belongsToMany(Profile::class, null, 'usuario_ids', 'perfil_ids');
    }
}
