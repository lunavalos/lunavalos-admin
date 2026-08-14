<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Cifra en reposo los tokens ya guardados en social_accounts y saca el
 * page_token del JSON `meta`.
 *
 * Contexto: `SocialAccount` acaba de recibir el cast `encrypted` en
 * access_token y refresh_token. Las filas existentes están en claro, así que
 * sin este backfill el cast lanzaría DecryptException al leerlas.
 *
 * El page_token de Facebook/Instagram duplicaba el valor de access_token y
 * viajaba sin cifrar hacia el navegador, porque `meta` no está en $hidden y
 * los controladores mandan el modelo completo a Inertia.
 *
 * Se salta las filas que ya estén cifradas, así que correrla dos veces no hace
 * daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_accounts')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $cambios = [];

                foreach (['access_token', 'refresh_token'] as $columna) {
                    $valor = $row->{$columna} ?? null;
                    if ($valor === null || $valor === '' || $this->yaCifrado($valor)) {
                        continue;
                    }
                    $cambios[$columna] = Crypt::encryptString($valor);
                }

                $meta = json_decode($row->meta ?? '', true);
                if (is_array($meta) && array_key_exists('page_token', $meta)) {
                    unset($meta['page_token']);
                    $cambios['meta'] = json_encode($meta);
                }

                if ($cambios !== []) {
                    DB::table('social_accounts')->where('id', $row->id)->update($cambios);
                }
            }
        });
    }

    /**
     * Devuelve los tokens a texto plano.
     *
     * No restaura meta.page_token: su valor es idéntico al de access_token y
     * los publishers de la versión anterior ya hacían fallback a esa columna.
     */
    public function down(): void
    {
        DB::table('social_accounts')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $cambios = [];

                foreach (['access_token', 'refresh_token'] as $columna) {
                    $valor = $row->{$columna} ?? null;
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    try {
                        $cambios[$columna] = Crypt::decryptString($valor);
                    } catch (DecryptException) {
                        // Ya estaba en claro.
                    }
                }

                if ($cambios !== []) {
                    DB::table('social_accounts')->where('id', $row->id)->update($cambios);
                }
            }
        });
    }

    private function yaCifrado(string $valor): bool
    {
        try {
            Crypt::decryptString($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
