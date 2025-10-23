<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UsuariosController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        // Obtener los usuarios con sus roles
        $users = User::with('roles')->get(['id', 'nombre', 'apellido_p', 'apellido_m', 'primer_login', 'email']);

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'roles' => $roles,
            'toast' => session('toast'),
        ]);
    }

    public function store(Request $request)
    {
        $anio_actual = '0' . date('Y');
        $password = 'tlaloc_' . $anio_actual;

        // Validación
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'rol_id' => 'required|exists:roles,id',
        ]);

        try {
            $user = User::create([
                'nombre' => $validated['nombre'],
                'apellido_p' => $validated['apellido_p'],
                'apellido_m' => $validated['apellido_m'] ?? null,
                'email' => $validated['email'],
                'password' => bcrypt($password),
                'primer_login' => 1,
            ]);

            $role = Role::find($validated['rol_id']);
            if ($role) {
                $user->assignRole($role);
            }
            return inertia()->location(
                route('usuarios.index') .
                    '?toast_message=' . urlencode("Usuario creado correctamente. Contraseña generada: {$password}") .
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            return inertia()->location(
                route('usuarios.index') .
                    '?toast_message=' . urlencode('Error al crear el usuario: ' . $e->getMessage()) .
                    '&toast_type=error'
            );
        }
    }

    public function update(Request $request, $id)
    {
        // Validación
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'rol_id' => 'required|exists:roles,id',
        ]);

        try {
            $user = User::findOrFail($id);

            $user->update([
                'nombre' => $validated['nombre'],
                'apellido_p' => $validated['apellido_p'],
                'apellido_m' => $validated['apellido_m'] ?? null,
                'email' => $validated['email'],
            ]);

            if (isset($validated['rol_id'])) {
                $role = Role::find($validated['rol_id']);
                if ($role) {
                    $user->syncRoles([$role]);
                }
            }

            // Para "forzar" la redirección y recarga en Inertia, devolver Inertia::location a la ruta
            return inertia()->location(
                route('usuarios.index') .
                    '?toast_message=' . urlencode('Usuario actualizado correctamente.') .
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            // Si hay error, devolver una recarga con el toast de error igualmente
            return inertia()->location(route('usuarios.index') . '?toast_message=' . urlencode('Error al actualizar el usuario: ' . $e->getMessage()) . '&toast_type=error');
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            // Éxito - Retornar Inertia::location para recarga y mostrar toast correcto
            return inertia()->location(
                route('usuarios.index') .
                    '?toast_message=' . urlencode('Usuario eliminado correctamente.') .
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            // Error - Retornar Inertia::location con mensaje de error
            return inertia()->location(
                route('usuarios.index') .
                    '?toast_message=' . urlencode('Error al eliminar el usuario: ' . $e->getMessage()) .
                    '&toast_type=error'
            );
        }
    }
}
