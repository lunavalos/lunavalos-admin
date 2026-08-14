<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las políticas públicas del sitio declaran que los tokens de las plataformas
 * están cifrados en reposo. Estas pruebas evitan que esa declaración se vuelva
 * falsa sin que nadie se entere.
 */
class SocialAccountTokenEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN   = 'EAAG-token-de-pagina-de-prueba';
    private const REFRESH = '1//refresh-de-prueba';

    private function cuenta(array $atributos = []): SocialAccount
    {
        $client = Client::create(['business_name' => 'Cliente de prueba']);

        return SocialAccount::create(array_merge([
            'client_id'        => $client->id,
            'provider'         => SocialAccount::PROVIDER_FACEBOOK,
            'provider_user_id' => '1234567890',
            'name'             => 'Página de prueba',
            'access_token'     => self::TOKEN,
            'refresh_token'    => self::REFRESH,
            'meta'             => ['page_id' => '1234567890', 'page_name' => 'Página de prueba'],
        ], $atributos));
    }

    public function test_los_tokens_no_se_guardan_en_claro(): void
    {
        $cuenta = $this->cuenta();

        $fila = DB::table('social_accounts')->where('id', $cuenta->id)->first();

        $this->assertNotSame(self::TOKEN, $fila->access_token);
        $this->assertNotSame(self::REFRESH, $fila->refresh_token);
        $this->assertStringNotContainsString('token-de-pagina', $fila->access_token);
        $this->assertStringNotContainsString('refresh-de-prueba', $fila->refresh_token);
    }

    public function test_los_tokens_se_leen_descifrados_desde_el_modelo(): void
    {
        $cuenta = $this->cuenta();

        $recargada = SocialAccount::findOrFail($cuenta->id);

        $this->assertSame(self::TOKEN, $recargada->access_token);
        $this->assertSame(self::REFRESH, $recargada->refresh_token);
    }

    public function test_los_tokens_no_viajan_en_la_serializacion(): void
    {
        $json = $this->cuenta()->toArray();

        $this->assertArrayNotHasKey('access_token', $json);
        $this->assertArrayNotHasKey('refresh_token', $json);
        // `meta` sí se serializa: por eso no debe contener credenciales.
        $this->assertArrayNotHasKey('page_token', $json['meta']);
    }

    public function test_un_refresh_token_nulo_no_rompe_el_cast(): void
    {
        $cuenta = $this->cuenta(['refresh_token' => null]);

        $this->assertNull(SocialAccount::findOrFail($cuenta->id)->refresh_token);
    }

    /**
     * El backfill corre contra filas que ya existen en producción: si se
     * equivoca, todos los clientes quedan desconectados.
     */
    public function test_la_migracion_cifra_las_filas_en_claro_y_es_idempotente(): void
    {
        $client = Client::create(['business_name' => 'Cliente heredado']);

        $id = DB::table('social_accounts')->insertGetId([
            'client_id'        => $client->id,
            'provider'         => SocialAccount::PROVIDER_FACEBOOK,
            'provider_user_id' => '999',
            'access_token'     => self::TOKEN,
            'refresh_token'    => self::REFRESH,
            'meta'             => json_encode(['page_id' => '999', 'page_token' => self::TOKEN]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $migracion = require database_path('migrations/2026_08_14_000000_encrypt_social_account_tokens.php');
        $migracion->up();

        $cuenta = SocialAccount::findOrFail($id);
        $this->assertSame(self::TOKEN, $cuenta->access_token);
        $this->assertSame(self::REFRESH, $cuenta->refresh_token);
        $this->assertArrayNotHasKey('page_token', $cuenta->meta);
        $this->assertSame('999', $cuenta->meta['page_id']);

        // Segunda pasada: no debe volver a cifrar lo ya cifrado.
        $cifrado = DB::table('social_accounts')->where('id', $id)->value('access_token');
        (require database_path('migrations/2026_08_14_000000_encrypt_social_account_tokens.php'))->up();

        $this->assertSame($cifrado, DB::table('social_accounts')->where('id', $id)->value('access_token'));
        $this->assertSame(self::TOKEN, SocialAccount::findOrFail($id)->access_token);
    }

    public function test_la_migracion_se_puede_revertir(): void
    {
        $cuenta = $this->cuenta();

        (require database_path('migrations/2026_08_14_000000_encrypt_social_account_tokens.php'))->down();

        $fila = DB::table('social_accounts')->where('id', $cuenta->id)->first();
        $this->assertSame(self::TOKEN, $fila->access_token);
        $this->assertSame(self::REFRESH, $fila->refresh_token);
    }
}
