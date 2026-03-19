<?php

namespace App\Http\Controllers;

use App\Models\SignatureTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignatureGeneratorController extends Controller
{
    public function index()
    {
        return Inertia::render('ClientPanel/Signatures', [
            'templates' => SignatureTemplate::where('is_active', true)->get()
        ]);
    }
}
