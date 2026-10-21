<?php

namespace App\Http\Controllers;

use App\Models\SignatureAsset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'slot'      => 'required|in:' . implode(',', SignatureAsset::SLOTS),
            'label'     => 'nullable|string|max:255',
            // Sin SVG: Outlook y Gmail no lo pintan.
            'file'      => 'required|file|mimes:png,jpg,jpeg,gif|max:2048',
            'link_url'  => 'nullable|url|max:2048',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        $file = $request->file('file');

        SignatureAsset::create([
            ...collect($data)->except('file')->all(),
            'file_path'  => $file->store('email-signatures/assets/' . $data['client_id'], 'public'),
            'mime'       => $file->getMimeType(),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Imagen subida. Las firmas de esta empresa ya la muestran cuando esté vigente.');
    }

    public function destroy(SignatureAsset $signatureAsset)
    {
        Storage::disk('public')->delete($signatureAsset->file_path);
        $signatureAsset->delete();

        return back()->with('success', 'Imagen eliminada.');
    }
}
