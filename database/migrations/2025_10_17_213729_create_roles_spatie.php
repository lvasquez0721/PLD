<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear los roles usando Spatie Laravel Permission
        $roles = [
            ['name' => 'Administrador', 'guard_name' => 'web'],
            ['name' => 'Oficial de cumplimiento', 'guard_name' => 'web'],
            ['name' => 'Servidor', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina todos los roles creados si existen
        $roleNames = [
            'Administrador',
            'Oficial de cumplimiento',
            'Servidor',
        ];

        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->delete();
            }
        }
    }
};
