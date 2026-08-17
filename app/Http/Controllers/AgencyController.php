<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AgencyController extends Controller implements HasMiddleware
{
    /** Catálogo de origen/agencia del cliente: parte del módulo de clientes. */
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Clientes', only: ['index']),
            new Middleware('can:Editar Clientes', only: ['store', 'update', 'destroy']),
        ];
    }

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
