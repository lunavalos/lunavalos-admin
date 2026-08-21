<?php

namespace App\Http\Controllers;

use App\Support\RolePreview;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Entrar y salir del modo "Ver como rol". Ver App\Support\RolePreview.
 */
class RolePreviewController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        // Se valida contra los roles REALES: un admin en modo "Cliente" sigue
        // siendo admin para efectos de este control.
        abort_unless(RolePreview::canPreview($user), 403);

        $data = $request->validate([
            'role' => ['required', 'string'],
        ]);

        if (! in_array($data['role'], RolePreview::previewableRoles($user), true)) {
            throw ValidationException::withMessages([
                'role' => 'Ese rol no está disponible para previsualizar.',
            ]);
        }

        RolePreview::start($data['role']);

        // Al dashboard y no `back()`: la pantalla actual puede ser justamente
        // una que el rol elegido no tiene permitida, y saldría un 403 en vez
        // del sistema visto con los ojos del rol.
        return redirect()->route('dashboard')
            ->with('warning', "Estás viendo el sistema como «{$data['role']}». Tus permisos reales están suspendidos hasta que salgas del modo.");
    }

    public function destroy(Request $request)
    {
        // Sin comprobación de permisos: salir del modo debe funcionar siempre,
        // incluso desde un rol previsualizado que no tenga permiso alguno.
        RolePreview::stop();

        return redirect()->route('dashboard')
            ->with('success', 'Volviste a tu rol real.');
    }
}
