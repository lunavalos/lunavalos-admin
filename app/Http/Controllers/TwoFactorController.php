<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laragear\TwoFactor\Models\TwoFactorAuthentication;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    /**
     * Show the 2FA challenge view.
     */
    public function showChallenge()
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Handle the 2FA challenge.
     */
    public function storeChallenge(Request $request)
    {
        $request->validate([
            'code' => 'required_without:recovery_code|string|nullable',
            'recovery_code' => 'required_without:code|string|nullable',
        ]);

        $id = $request->session()->get('two_factor_user_id');
        if (!$id) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::findOrFail($id);

        if ($request->code) {
            if ($user->validateTwoFactorCode($request->code)) {
                if (config('two-factor.safe_devices.enabled')) {
                    $user->addSafeDevice($request);
                }
                Auth::login($user, $request->session()->get('two_factor_remember', false));
                $request->session()->forget(['two_factor_user_id', 'two_factor_remember']);
                $request->session()->put('two_factor_auth_passed', true);
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard'));
            }
        } elseif ($request->recovery_code) {
            if ($user->useRecoveryCode($request->recovery_code)) {
                if (config('two-factor.safe_devices.enabled')) {
                    $user->addSafeDevice($request);
                }
                Auth::login($user, $request->session()->get('two_factor_remember', false));
                $request->session()->forget(['two_factor_user_id', 'two_factor_remember']);
                $request->session()->put('two_factor_auth_passed', true);
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard'));
            }
        }

        return back()->withErrors(['code' => 'El código proporcionado es inválido o ha expirado.']);
    }

    /**
     * Enable 2FA for the user.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // Create a new 2FA setup but don't enable it yet (need to verify)
        // We can just create it if not exists or if not enabled
        if (!$user->twoFactorAuth?->id) {
            $user->createTwoFactorAuth();
        }

        $qrCode = $user->twoFactorAuth->toQr();

        return response()->json([
            'qrCode' => $qrCode,
            'secret' => $user->twoFactorAuth->shared_secret,
        ]);
    }

    /**
     * Confirm and activate 2FA for the user.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        if ($request->user()->confirmTwoFactorAuth($request->code)) {
            return back()->with('status', 'two-factor-enabled');
        }

        return back()->withErrors(['code' => 'El código proporcionado es inválido.']);
    }

    /**
     * Disable 2FA for the user.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $request->user()->disableTwoFactorAuth();

        return back()->with('status', 'two-factor-disabled');
    }
    
    /**
     * Get recovery codes.
     */
    public function recoveryCodes(Request $request)
    {
        if (!$request->user()->twoFactorAuth) {
            return back();
        }
        
        return response()->json([
            'recoveryCodes' => $request->user()->twoFactorAuth->recovery_codes,
        ]);
    }
}
