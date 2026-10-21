<?php

namespace App\Http\Controllers;

use App\Models\SignatureAsset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignatureAssetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Gestionar Firmas de Empleados'),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, fileRequired: true);
        $file = $request->file('file');

        SignatureAsset::create([
            ...collect($data)->except('file')->all(),
            'file_path'  => $file->store('email-signatures/assets/' . $data['client_id'], 'public'),
            'mime'       => $file->getMimeType(),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Imagen subida. Las firmas de esta empresa ya la muestran cuando esté vigente.');
    }

    /**
     * Cambia fechas, plantilla, nombre o link; la imagen solo si se manda una.
     * Las firmas no guardan la ruta del archivo (piden /firma/{token}/img/...),
     * así que reemplazarla no rompe nada de lo ya pegado.
     */
    public function update(Request $request, SignatureAsset $signatureAsset)
    {
        $data = $this->validated($request, fileRequired: false);
        $data = collect($data)->except(['file', 'client_id'])->all();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $old = $signatureAsset->file_path;

            $data['file_path'] = $file->store('email-signatures/assets/' . $signatureAsset->client_id, 'public');
            $data['mime'] = $file->getMimeType();

            Storage::disk('public')->delete($old);
        }

        $signatureAsset->update($data);

        return back()->with('success', 'Imagen actualizada.');
    }

    public function destroy(SignatureAsset $signatureAsset)
    {
        Storage::disk('public')->delete($signatureAsset->file_path);
        $signatureAsset->delete();

        return back()->with('success', 'Imagen eliminada.');
    }

    private function validated(Request $request, bool $fileRequired): array
    {
        $data = $request->validate([
            'client_id' => $fileRequired ? 'required|exists:clients,id' : 'nullable',
            // Vacío = aplica a todas las plantillas del cliente.
            'signature_template_id' => 'nullable|exists:signature_templates,id',
            'slot'      => 'required|in:' . implode(',', SignatureAsset::SLOTS),
            'label'     => 'nullable|string|max:255',
            // Sin SVG: Outlook y Gmail no lo pintan.
            'file'      => ($fileRequired ? 'required' : 'nullable') . '|file|mimes:png,jpg,jpeg,gif|max:2048',
            'link_url'  => 'nullable|url|max:2048',
            'starts_at' => 'nullable|date|required_if_accepted:repeats_yearly',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at|required_if_accepted:repeats_yearly',
            'repeats_yearly' => 'boolean',
        ], [
            'starts_at.required_if_accepted' => 'Para repetir cada año indica desde cuándo.',
            'ends_at.required_if_accepted'   => 'Para repetir cada año indica hasta cuándo.',
        ]);

        // Una temporada que se repite no puede durar un año o más: estaría
        // vigente siempre y taparía al logo de siempre.
        if (($data['repeats_yearly'] ?? false)
            && Carbon::parse($data['ends_at'])->gte(Carbon::parse($data['starts_at'])->addYear())) {
            throw ValidationException::withMessages(['ends_at' => 'Una temporada que se repite cada año debe durar menos de un año.']);
        }

        $data['repeats_yearly'] = (bool) ($data['repeats_yearly'] ?? false);

        return $data;
    }
}
