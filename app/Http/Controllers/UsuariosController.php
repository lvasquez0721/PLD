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
        $users = User::with('roles')->get(['id', 'nombre', 'apellido_p', 'apellido_m', 'primer_login', 'email', 'usuario']);

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'roles' => $roles,
            'toast' => session('toast'),
        ]);
    }

    public function store(Request $request)
    {
        $anio_actual = '0'.date('Y');
        $password = 'tlaloc_'.$anio_actual;

        // Validación
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'rol_id' => 'required|exists:roles,id',
        ]);

        // Generar el campo "usuario" con una nomenclatura: primera letra nombre + apellido_p (sin espacios ni tildes) + año actual en dos dígitos
        $nombre_letra = strtoupper(substr($validated['nombre'], 0, 1));
        $apellido_p_saneado = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $validated['apellido_p'])));
        $usuario_generado_base = $nombre_letra.$apellido_p_saneado;

        // Checar unicidad, si existe, agregar número incremental al final
        $usuario_generado = $usuario_generado_base;
        $count = 1;
        while (User::where('usuario', $usuario_generado)->exists()) {
            $usuario_generado = $usuario_generado_base.$count;
            $count++;
        }

        $usuario_generado = strtolower($usuario_generado);

        try {
            $user = User::create([
                'usuario' => $usuario_generado,
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
                route('usuarios.index').
                    '?toast_message='.urlencode("Usuario creado correctamente. Usuario: {$usuario_generado} - Contraseña generada: {$password}").
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            return inertia()->location(
                route('usuarios.index').
                    '?toast_message='.urlencode('Error al crear el usuario: '.$e->getMessage()).
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
            'email' => 'required|email|unique:users,email,'.$id,
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
                route('usuarios.index').
                    '?toast_message='.urlencode('Usuario actualizado correctamente.').
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            // Si hay error, devolver una recarga con el toast de error igualmente
            return inertia()->location(route('usuarios.index').'?toast_message='.urlencode('Error al actualizar el usuario: '.$e->getMessage()).'&toast_type=error');
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            // Éxito - Retornar Inertia::location para recarga y mostrar toast correcto
            return inertia()->location(
                route('usuarios.index').
                    '?toast_message='.urlencode('Usuario eliminado correctamente.').
                    '&toast_type=success'
            );
        } catch (\Exception $e) {
            // Error - Retornar Inertia::location con mensaje de error
            return inertia()->location(
                route('usuarios.index').
                    '?toast_message='.urlencode('Error al eliminar el usuario: '.$e->getMessage()).
                    '&toast_type=error'
            );
        }
    }
}
