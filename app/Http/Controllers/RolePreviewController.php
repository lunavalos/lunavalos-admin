<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\RolePreview;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Entrar y salir del modo "Ver como". Ver App\Support\RolePreview.
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
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['string'],
            'client_id' => ['nullable', 'integer'],
        ]);

        $permitidos = RolePreview::previewableRoles($user);

        if (array_diff($data['roles'], $permitidos)) {
            throw ValidationException::withMessages([
                'roles' => 'Alguno de esos roles no está disponible para previsualizar.',
            ]);
        }

        $clientId = $data['client_id'] ?? null;

        if ($clientId !== null) {
            if (! RolePreview::canBindClient($user)) {
                throw ValidationException::withMessages([
                    'client_id' => 'No puedes acotar el preview a un cliente.',
                ]);
            }

            if (! Client::query()->whereKey($clientId)->exists()) {
                throw ValidationException::withMessages([
                    'client_id' => 'Ese cliente no existe.',
                ]);
            }
        }

        RolePreview::start($data['roles'], $clientId);

        // Al dashboard y no `back()`: la pantalla actual puede ser justamente
        // una que los roles elegidos no tienen permitida, y saldría un 403 en
        // vez del sistema visto con los ojos de ese rol.
        return redirect()->route('dashboard')->with(
            'warning',
            'Estás viendo el sistema como «' . implode(' + ', $data['roles']) . '». '
            . 'Tus permisos reales están suspendidos hasta que salgas del modo.'
        );
    }

    public function destroy(Request $request)
    {
        // Sin comprobación de permisos: salir del modo debe funcionar siempre,
        // incluso desde un rol previsualizado que no tenga permiso alguno.
        RolePreview::stop();

        return redirect()->route('dashboard')->with('success', 'Volviste a tu rol real.');
    }
}
