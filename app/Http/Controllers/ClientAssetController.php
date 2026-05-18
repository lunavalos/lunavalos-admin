<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientAssetController extends Controller
{
    private function denyClient(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->hasRole('Cliente')) {
            abort(403, 'Los clientes no pueden modificar los documentos del cliente.');
        }
    }

    public function store(Request $request, Client $client)
    {
        $this->denyClient();
        $data = $request->validate([
            'kind'  => 'required|string|in:' . implode(',', ClientAsset::KINDS),
            'label' => 'required|string|max:255',
            'file'  => 'nullable|file|max:20480',
            'url'   => 'nullable|url|max:2048',
            'value' => 'nullable|array',
        ]);

        $asset = new ClientAsset([
            'client_id'  => $client->id,
            'kind'       => $data['kind'],
            'label'      => $data['label'],
            'url'        => $data['url'] ?? null,
            'value'      => $data['value'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $asset->file_path = $file->store('client-assets', 'public');
            $asset->file_name = $file->getClientOriginalName();
            $asset->mime      = $file->getMimeType();
        }

        $asset->save();

        return back()->with('success', 'Activo agregado.');
    }

    public function update(Request $request, ClientAsset $asset)
    {
        $this->denyClient();
        $data = $request->validate([
            'kind'  => 'required|string|in:' . implode(',', ClientAsset::KINDS),
            'label' => 'required|string|max:255',
            'file'  => 'nullable|file|max:20480',
            'url'   => 'nullable|url|max:2048',
            'value' => 'nullable|array',
        ]);

        $asset->fill([
            'kind'  => $data['kind'],
            'label' => $data['label'],
            'url'   => $data['url'] ?? null,
            'value' => $data['value'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            if ($asset->file_path) {
                Storage::disk('public')->delete($asset->file_path);
            }
            $file = $request->file('file');
            $asset->file_path = $file->store('client-assets', 'public');
            $asset->file_name = $file->getClientOriginalName();
            $asset->mime      = $file->getMimeType();
        }

        $asset->save();

        return back()->with('success', 'Activo actualizado.');
    }

    public function destroy(ClientAsset $asset)
    {
        $this->denyClient();
        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }
        $asset->delete();

        return back()->with('success', 'Activo eliminado.');
    }
}
