<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\ProfilePhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactorEnabled' => $request->user()->hasTwoFactorEnabled(),
            // `vault_credentials` está en `User::$hidden`, así que no llega por
            // `auth.user`. Se manda solo en esta página, que es la que la edita.
            'vaultCredentials' => $request->user()->vault_credentials,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->hasFile('photo')) {
            // El avatar va en el layout, así que viaja en cada vista: se guarda
            // reducido en vez de tal cual lo subió el usuario.
            $request->user()->profile_photo_path = ProfilePhoto::store($request->file('photo'));
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Update the user's vault records.
     */
    public function updateVault(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vault_credentials' => ['nullable', 'string']
        ]);

        $request->user()->forceFill([
            'vault_credentials' => $validated['vault_credentials'] ?? ''
        ])->save();

        return Redirect::route('profile.edit')->with('status', 'vault-updated');
    }

    /**
     * Verify the user's password for unlocking vaults.
     */
    public function verifyPassword(Request $request): RedirectResponse
    {
        if (!\Illuminate\Support\Facades\Hash::check($request->password, $request->user()->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => 'La contraseña es incorrecta.',
            ]);
        }

        return back();
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
