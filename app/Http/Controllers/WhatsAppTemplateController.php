<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * Plantillas de mensaje por WABA.
 *
 * Existen por dos razones que apuntan al mismo sitio: fuera de la ventana de
 * 24 h son la única forma de escribirle a un contacto, y Meta exige un video
 * creando una para aprobar whatsapp_business_management.
 */
class WhatsAppTemplateController extends Controller
{
    /**
     * Las cuentas que este usuario puede operar.
     *
     * Un usuario amarrado a un cliente (`users.client_id`) solo alcanza las
     * WABAs que tienen algún número suyo. Mismo criterio que
     * ConversationController y WhatsAppConnectController.
     */
    private function cuentasVisibles(Request $request)
    {
        $propio = $request->user()?->client_id;

        return WhatsAppAccount::query()
            ->when($propio, fn ($q) => $q->whereHas(
                'numbers',
                fn ($n) => $n->where('client_id', $propio)
            ))
            ->orderBy('name');
    }

    private function autorizar(Request $request, WhatsAppAccount $cuenta): void
    {
        $propio = $request->user()?->client_id;

        abort_if(
            $propio !== null && !$cuenta->numbers()->where('client_id', $propio)->exists(),
            403,
            'Esa cuenta de WhatsApp no pertenece a este cliente.'
        );
    }

    public function index(Request $request)
    {
        $cuentas = $this->cuentasVisibles($request)->with('numbers:id,whatsapp_account_id,display_phone_number')->get();

        $seleccionada = $request->integer('cuenta')
            ? $cuentas->firstWhere('id', $request->integer('cuenta'))
            : $cuentas->first();

        return Inertia::render('WhatsApp/Templates', [
            'cuentas' => $cuentas->map(fn (WhatsAppAccount $c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'waba_id' => $c->waba_id,
                'status'  => $c->status,
                'numeros' => $c->numbers->pluck('display_phone_number'),
            ]),
            'cuentaId'   => $seleccionada?->id,
            'plantillas' => $seleccionada
                ? $this->plantillasDe($seleccionada)
                : [],
            'categorias' => WhatsAppTemplate::CATEGORIAS,
        ]);
    }

    /**
     * Vuelve a leer las plantillas desde Meta. Una plantilla puede crearse o
     * deshabilitarse desde el Business Manager del cliente sin pasar por aquí.
     */
    public function sync(Request $request, WhatsAppAccount $account, WhatsAppTemplateService $servicio)
    {
        $this->autorizar($request, $account);

        try {
            $cuantas = $servicio->sincronizar($account);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }

        return back()->with('success', "Se sincronizaron {$cuantas} plantillas.");
    }

    public function store(Request $request, WhatsAppAccount $account, WhatsAppTemplateService $servicio)
    {
        $this->autorizar($request, $account);

        $datos = $request->validate([
            // Meta solo acepta minúsculas, dígitos y guiones bajos.
            'name'     => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => 'required|string|max:20',
            'category' => 'required|in:' . implode(',', WhatsAppTemplate::CATEGORIAS),
            'header'   => 'nullable|string|max:60',
            'body'     => 'required|string|max:1024',
            'footer'   => 'nullable|string|max:60',
        ], [
            'name.regex' => 'El nombre solo admite minúsculas, números y guiones bajos.',
        ]);

        try {
            $servicio->crear($account, $datos);
        } catch (RuntimeException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return back()->with('success', 'Plantilla enviada a revisión de Meta.');
    }

    public function destroy(Request $request, WhatsAppTemplate $template, WhatsAppTemplateService $servicio)
    {
        $this->autorizar($request, $template->account);

        try {
            $servicio->eliminar($template);
        } catch (RuntimeException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return back()->with('success', 'Plantilla eliminada.');
    }

    private function plantillasDe(WhatsAppAccount $cuenta): array
    {
        return $cuenta->templates()
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppTemplate $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'language'        => $p->language,
                'category'        => $p->category,
                'status'          => $p->status,
                'rejected_reason' => $p->rejected_reason,
                'body'            => $p->cuerpo(),
                'body_variables'  => $p->body_variables,
            ])
            ->all();
    }
}
