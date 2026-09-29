<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Crea el rol "Depurador" y el usuario de servicio con acceso
     * exclusivo al módulo de Logs (/logs).
     *
     * Credenciales: usuario `depurador` / contraseña `debug123`.
     */
    public function up(): void
    {
        $roleClass = \Spatie\Permission\Models\Role::class;
        $userClass = \App\Models\User::class;

        $roleClass::firstOrCreate(['name' => 'Depurador', 'guard_name' => 'web']);

        $user = $userClass::firstOrCreate(
            ['email' => 'depurador@tlalocseguros.com'],
            [
                'usuario' => 'depurador',
                'nombre' => 'Depurador',
                'apellido_p' => 'Sistema',
                'apellido_m' => '.',
                'primer_login' => false,
                'email_verified_at' => now(),
                'password' => Hash::make('debug123'),
            ]
        );

        if (! $user->hasRole('Depurador')) {
            $user->assignRole('Depurador');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleClass = \Spatie\Permission\Models\Role::class;
        $userClass = \App\Models\User::class;

        $user = $userClass::where('email', 'depurador@tlalocseguros.com')->first();
        if ($user) {
            if ($user->hasRole('Depurador')) {
                $user->removeRole('Depurador');
            }
            $user->delete();
        }

        $role = $roleClass::where('name', 'Depurador')->where('guard_name', 'web')->first();
        if ($role) {
            $role->delete();
        }
    }
};
