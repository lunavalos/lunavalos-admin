<?php

namespace App\Services\Facturama;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Wrapper HTTP para la API de Facturama.
 *
 * Endpoints utilizados:
 *  - POST /3/cfdis           Emisión CFDI 4.0
 *  - GET  /cfdi/{id}         Obtener CFDI
 *  - GET  /cfdi/xml/issued/{id}
 *  - GET  /cfdi/pdf/issued/{id}
 *  - DELETE /cfdi/{id}?type=issued&motive=02   Cancelación
 *
 * Docs: https://apisandbox.facturama.mx/Guides
 */
class FacturamaClient
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $user    = null,
        protected ?string $password = null,
        protected int $timeout = 30,
    ) {
        $this->baseUrl  = rtrim($baseUrl  ?? (string) config('facturama.base_url'), '/');
        $this->user     = $user     ?? config('facturama.api_user');
        $this->password = $password ?? config('facturama.api_password');
        $this->timeout  = $timeout  ?: (int) config('facturama.timeout', 30);
    }

    public function isConfigured(): bool
    {
        return $this->user && $this->password && $this->baseUrl;
    }

    protected function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facturama no está configurado (revisa FACTURAMA_API_USER/PASSWORD).');
        }

        return Http::withBasicAuth($this->user, $this->password)
            ->acceptJson()
            ->timeout($this->timeout)
            ->baseUrl($this->baseUrl);
    }

    public function issueCfdi(array $payload): array
    {
        $response = $this->http()->post('/3/cfdis', $payload);
        $this->ensureOk($response, 'No se pudo timbrar el CFDI');
        return $response->json();
    }

    public function getCfdi(string $id): array
    {
        $response = $this->http()->get("/cfdi/{$id}");
        $this->ensureOk($response, 'No se pudo consultar el CFDI');
        return $response->json();
    }

    public function downloadXml(string $id): string
    {
        $response = $this->http()->get("/cfdi/xml/issued/{$id}");
        $this->ensureOk($response, 'No se pudo descargar el XML');
        $data = $response->json();
        return base64_decode($data['Content'] ?? '');
    }

    public function downloadPdf(string $id): string
    {
        $response = $this->http()->get("/cfdi/pdf/issued/{$id}");
        $this->ensureOk($response, 'No se pudo descargar el PDF');
        $data = $response->json();
        return base64_decode($data['Content'] ?? '');
    }

    public function cancelCfdi(string $id, string $motive = '02', ?string $substitution = null): array
    {
        $query = http_build_query(array_filter([
            'type'   => 'issued',
            'motive' => $motive,
            'uuidReplacement' => $substitution,
        ]));

        $response = $this->http()->delete("/cfdi/{$id}?{$query}");
        $this->ensureOk($response, 'No se pudo cancelar el CFDI');
        return $response->json();
    }

    protected function ensureOk(Response $response, string $message): void
    {
        if ($response->failed()) {
            throw new \RuntimeException($message . ': ' . $response->status() . ' ' . $response->body());
        }
    }
}
