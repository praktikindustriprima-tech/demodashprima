<?php

namespace App\Http\Controllers;

use App\Models\ExcludedOnu;
use Illuminate\Http\Request;

class ExcludedOnuController extends Controller
{
    /**
     * List all excluded SNs.
     */
    public function index()
    {
        $excluded = ExcludedOnu::orderByDesc('created_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $excluded,
        ]);
    }

    /**
     * Add one or more SNs to the exclude list.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sn' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $sn = strtoupper(trim($request->sn));

        $excluded = ExcludedOnu::updateOrCreate(
            ['sn' => $sn],
            ['notes' => $request->notes],
        );

        return response()->json([
            'status' => 'success',
            'data' => $excluded,
        ]);
    }

    /**
     * Remove an SN from the exclude list.
     */
    public function destroy(ExcludedOnu $excludedOnu)
    {
        $excludedOnu->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Excluded SN removed.',
        ]);
    }
}
