<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCost;
use Illuminate\Http\Request;

class ClientCostController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'billing_frequency' => 'required|string|in:monthly,annual,unique',
        ]);

        $client->costs()->create($validated);

        return redirect()->back()->with('message', 'Costo interno agregado existosamente.');
    }

    public function update(Request $request, Client $client, ClientCost $cost)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'billing_frequency' => 'required|string|in:monthly,annual,unique',
        ]);

        $cost->update($validated);

        return redirect()->back()->with('message', 'Costo interno actualizado exitosamente.');
    }

    public function destroy(Client $client, ClientCost $cost)
    {
        $cost->delete();
        return redirect()->back()->with('message', 'Costo interno eliminado exitosamente.');
    }
}
