<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    /**
     * Show the form for editing settings.
     */
    public function edit()
    {
        // Require admin, or if you don't have roles middleware, check here
        if (!auth()->user()->hasAnyRole(['Administrador', 'Administrador Master'])) {
            abort(403);
        }

        $settings = Setting::pluck('value', 'key')->toArray();

        // Si no existe contract_template, damos un texto base por default para que no esté vacío.
        if (!isset($settings['contract_template'])) {
            $settings['contract_template'] = "En la ciudad correspondiente, el día **[fecha]**, \ncelebran el presente contrato por una parte **[mi_razon_social]**, a quien en lo sucesivo se le denominará \"EL PRESTADOR\", \ny por la otra parte **[razon_social]** representada por el (la) \nC. **[representante]**, a quien en lo sucesivo se le denominará \"EL CLIENTE\".\n\n\"EL CLIENTE\" declara tener su domicilio fiscal ubicado en: **[direccion] (C.P. [codigo_postal])**, \ny contar con su Registro Federal de Contribuyentes: **[rfc]**.\n\n### Cláusula 1: Objeto\n\"EL PRESTADOR\" se obliga a brindar a \"EL CLIENTE\" los servicios listados a continuación, de conformidad con lo aprobado en la cotización:\n\n[lista_servicios]\n\n### Cláusula 2: Honorarios y Pagos\nSe establecen los siguientes montos por concepto de la prestación de servicios:\n\n[inversion_inicial]\n[inversion_mensual]\n\n### Cláusula 3: Duración\nLa duración acordada para los presentes servicios será de: **[duracion]**, \nsiendo vigente a partir de su aceptación formal.";
        }

        return Inertia::render('Settings/Edit', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        if (!auth()->user()->hasAnyRole(['Administrador', 'Administrador Master'])) {
            abort(403);
        }

        $validated = $request->validate([
            'contract_template' => 'nullable|string',
            'company_legal_name' => 'nullable|string',
            'company_commercial_name' => 'nullable|string',
            'company_rfc' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_commercial_address' => 'nullable|string',
            'company_work_days' => 'nullable|string',
            'company_work_start' => 'nullable|string',
            'company_work_end' => 'nullable|string',
            'company_email' => 'nullable|string|email',
            'company_phone' => 'nullable|string',
            'company_logo' => 'nullable|image|max:2048',
            'company_fb' => 'nullable|string',
            'company_x' => 'nullable|string',
            'company_linkedin' => 'nullable|string',
            'company_ig' => 'nullable|string',
            'company_tiktok' => 'nullable|string',
            'company_yt' => 'nullable|string',
            'smtp_username' => 'nullable|string|email',
            'smtp_from_address' => 'nullable|string|email',
            'smtp_password' => 'nullable|string',
        ]);

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'company_logo'],
                ['value' => $path]
            );
            unset($validated['company_logo']);
        }

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        return back()->with('success', 'Configuraciones guardadas del contrato y empresa.');
    }
}
