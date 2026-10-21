<?php

namespace App\Http\Controllers;

use App\Models\SignatureMedia;
use App\Models\SignatureTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Biblioteca de imágenes del editor de plantillas de firma.
 *
 * Responde JSON: se usa desde el editor sin recargar la página, para no
 * perder el HTML que se está escribiendo.
 */
class SignatureMediaController extends Controller
{
    // Sin SVG ni WebP: Outlook de escritorio no los pinta.
    private const MIMES = 'mimes:png,jpg,jpeg,gif';

    public function index(Request $request)
    {
        $this->authorizeEditor();

        $request->validate([
            'template_id' => 'nullable|exists:signature_templates,id',
            'draft_key'   => 'nullable|string|max:64',
        ]);

        return response()->json([
            'media' => $this->scoped($request)->latest('id')->get()->map->toLibraryArray(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'template_id' => 'nullable|required_without:draft_key|exists:signature_templates,id',
            'draft_key'   => 'nullable|required_without:template_id|string|max:64',
            'files'       => 'required|array|max:20',
            'files.*'     => 'file|' . self::MIMES . '|max:2048',
        ]);

        $created = collect($request->file('files'))->map(function (UploadedFile $file) use ($data) {
            $path = $file->storeAs(
                'signature-media',
                Str::uuid() . '.' . strtolower($file->guessExtension() ?: $file->getClientOriginalExtension()),
                'public'
            );

            return SignatureMedia::create([
                'signature_template_id' => $data['template_id'] ?? null,
                'draft_key'             => isset($data['template_id']) ? null : $data['draft_key'],
                'name'                  => $file->getClientOriginalName(),
                'file_path'             => $path,
                'created_by'            => Auth::id(),
                ...$this->fileInfo($file),
            ])->toLibraryArray();
        });

        return response()->json(['media' => $created], 201);
    }

    /**
     * Cambia el archivo conservando la URL: las firmas que ya lo usan (aunque
     * estén pegadas en Outlook/Gmail) muestran el nuevo.
     */
    public function replace(Request $request, SignatureMedia $signatureMedia)
    {
        $this->authorizeEditor();

        $request->validate(['file' => 'required|file|' . self::MIMES . '|max:2048']);

        $file = $request->file('file');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        // El servidor web decide el Content-Type por la extensión de la URL;
        // cambiar PNG por JPG en la misma URL lo rompería.
        if ($extension !== pathinfo($signatureMedia->file_path, PATHINFO_EXTENSION)) {
            return response()->json([
                'message' => 'Para conservar la URL el archivo nuevo debe ser del mismo tipo (.' . pathinfo($signatureMedia->file_path, PATHINFO_EXTENSION) . ').',
            ], 422);
        }

        Storage::disk('public')->putFileAs(
            dirname($signatureMedia->file_path),
            $file,
            basename($signatureMedia->file_path)
        );

        $signatureMedia->update(['name' => $file->getClientOriginalName(), ...$this->fileInfo($file)]);

        return response()->json(['media' => $signatureMedia->toLibraryArray()]);
    }

    public function destroy(SignatureMedia $signatureMedia)
    {
        $this->authorizeEditor();

        $usedIn = SignatureTemplate::where('html_content', 'like', '%' . $signatureMedia->file_path . '%')->pluck('name');

        if ($usedIn->isNotEmpty()) {
            return response()->json([
                'message' => 'La imagen se usa en: ' . $usedIn->implode(', ') . '. Quítala del HTML antes de eliminarla.',
            ], 422);
        }

        Storage::disk('public')->delete($signatureMedia->file_path);
        $signatureMedia->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeEditor(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->can('Crear Plantillas de Firma') || $user->can('Editar Plantillas de Firma'),
            403
        );
    }

    private function scoped(Request $request)
    {
        if ($request->filled('template_id')) {
            return SignatureMedia::where('signature_template_id', $request->integer('template_id'));
        }

        return SignatureMedia::whereNull('signature_template_id')
            ->where('draft_key', (string) $request->input('draft_key'));
    }

    private function fileInfo(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());

        return [
            'mime'   => $file->getMimeType(),
            'width'  => $info[0] ?? null,
            'height' => $info[1] ?? null,
            'size'   => $file->getSize(),
        ];
    }
}
