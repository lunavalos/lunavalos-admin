<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceTwoFactorActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La cuenta que se entrega a los revisores de plataforma debe poder entrar sin
 * 2FA. Sin esta excepción, un revisor de Meta inicia sesión y solo ve la
 * pantalla de perfil pidiéndole activar la autenticación en dos pasos —en
 * español— y nunca llega a la app que vino a revisar.
 */
class PlatformReviewerAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El middleware se salta a sí mismo en `testing`, así que hay que fingir
     * otro entorno para ejercitarlo de verdad.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'production');
    }

    private function pasarPorElMiddleware(User $user): Response
    {
        $request = Request::create('/social');
        $request->setUserResolver(fn () => $user);

        return (new EnforceTwoFactorActivation())
            ->handle($request, fn () => new Response('ok'));
    }

    private function usuarioConRol(string $rol): User
    {
        Role::findOrCreate($rol, 'web');

        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    public function test_el_revisor_de_plataforma_entra_sin_2fa(): void
    {
        $user = $this->usuarioConRol(config('roles.reviewer'));

        $respuesta = $this->pasarPorElMiddleware($user);

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertSame('ok', $respuesta->getContent());
    }

    public function test_el_staff_sin_2fa_sigue_obligado_a_activarlo(): void
    {
        $user = $this->usuarioConRol('Web Developer');

        $respuesta = $this->pasarPorElMiddleware($user);

        $this->assertSame(302, $respuesta->getStatusCode());
        $this->assertStringContainsString(route('profile.edit'), $respuesta->headers->get('Location'));
    }
}
