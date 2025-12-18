<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear usuario principal y asignar el rol de "Administrador" con Spatie

        // Importar clases necesarias dentro del método ya que no podemos usar import arriba
        $userClass = \App\Models\User::class;
        $roleClass = \Spatie\Permission\Models\Role::class;

        // Crear usuario solo si no existe el correo
        $admin = $userClass::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'usuario' => 'aPrincipal',
                'nombre' => 'Admin',
                'apellido_p' => 'Principal',
                'apellido_m' => '.',
                'primer_login' => true,
                'password' => bcrypt('contraseña123'), // Asegúrate de cambiar esta contraseña en producción
            ]
        );

        // Asegurar existencia del rol "Administrador"
        $role = $roleClass::firstOrCreate(
            ['name' => 'Administrador', 'guard_name' => 'web']
        );

        // Asignar el rol "Administrador" al usuario (solo si no lo tiene)
        if (!$admin->hasRole('Administrador')) {
            $admin->assignRole('Administrador');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el usuario admin@admin.com y su rol asignado
        $userClass = \App\Models\User::class;
        $roleClass = \Spatie\Permission\Models\Role::class;

        // Buscar el usuario admin
        $admin = $userClass::where('email', 'admin@admin.com')->first();
        if ($admin) {
            // Remover todos sus roles primero
            $admin->roles()->detach();
            // Eliminar usuario
            $admin->delete();
        }

        // Opcionalmente eliminar el rol "Administrador" (descomentar si se desea eliminar el rol global)
        /*
        $role = $roleClass::where('name', 'Administrador')->where('guard_name', 'web')->first();
        if ($role) {
            $role->delete();
        }
        */
    }
};
