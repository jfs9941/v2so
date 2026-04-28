<?php

namespace Module\Upload\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Module\Upload\Model\UploadDiagnostic;

class UploadDiagnosticController extends Controller
{
    public function index()
    {
        return view('upload.diagnostic');
    }

    public function show(string $id)
    {
        $diagnostic = UploadDiagnostic::findOrFail($id);

        return view('upload.diagnostic_show', [
            'diagnostic' => $diagnostic,
        ]);
    }

    public function store(Request $request)
    {

        $diagnostic = UploadDiagnostic::create([
            'user_id' => auth()->id(),
            'data' => $request->all(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $diagnostic,
        ]);
    }
}