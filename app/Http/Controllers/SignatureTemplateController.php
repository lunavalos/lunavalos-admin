<?php

namespace App\Http\Controllers;

use App\Models\SignatureTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignatureTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('SignatureTemplates/Index', [
            'templates' => SignatureTemplate::latest()->get()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('SignatureTemplates/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:signature_templates,slug',
            'html_content' => 'required|string',
            'css_content' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        SignatureTemplate::create($validated);

        return redirect()->route('signature-templates.index')
            ->with('success', 'Plantilla de firma creada correctamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SignatureTemplate $signatureTemplate)
    {
        return Inertia::render('SignatureTemplates/Edit', [
            'template' => $signatureTemplate
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SignatureTemplate $signatureTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:signature_templates,slug,' . $signatureTemplate->id,
            'html_content' => 'required|string',
            'css_content' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $signatureTemplate->update($validated);

        return redirect()->route('signature-templates.index')
            ->with('success', 'Plantilla de firma actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SignatureTemplate $signatureTemplate)
    {
        $signatureTemplate->delete();

        return redirect()->route('signature-templates.index')
            ->with('success', 'Plantilla de firma eliminada.');
    }
}
