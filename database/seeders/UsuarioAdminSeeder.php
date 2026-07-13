<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        $perfilAdmin = Profile::firstOrCreate(
            ['nombre_perfil' => 'Administrador'],
            [
                'secciones_permitidas' => ['productos', 'usuarios', 'perfiles', 'auditoria']
            ]
        );

        User::updateOrCreate(
            ['correo_electronico' => 'admin@tap.com'],
            [
                'usuario' => 'admin',
                'nombre_completo' => 'Administrador TAP',
                'password' => Hash::make('010704'),
                'telefono' => '+521234567890',
                'foto_perfil' => null,
                'perfil_ids' => [$perfilAdmin->_id]
            ]
        );

        $this->command->info('¡Perfil y Usuario Administrador verificados/creados con éxito en MongoDB!');
    }
}
