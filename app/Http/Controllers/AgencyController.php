<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::orderBy('name')->get();
        return Inertia::render('Agencies/Index', [
            'agencies' => $agencies
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:agencies'
        ]);

        Agency::create($request->all());

        return redirect()->route('agencies.index')->with('message', 'Agencia/Origen guardada exitosamente.');
    }

    public function update(Request $request, Agency $agency)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:agencies,name,' . $agency->id
        ]);

        $agency->update($request->all());

        return redirect()->route('agencies.index')->with('message', 'Agencia/Origen actualizada exitosamente.');
    }

    public function destroy(Agency $agency)
    {
        $agency->delete();
        return redirect()->route('agencies.index')->with('message', 'Agencia/Origen eliminada exitosamente.');
    }
}
