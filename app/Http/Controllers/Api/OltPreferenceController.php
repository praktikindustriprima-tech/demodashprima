<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OltPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OltPreferenceController extends Controller
{
    /**
     * Get all user preferences.
     *
     * Returns a key-value map of all preferences for the
     * authenticated user. Values stored as JSON are
     * automatically decoded in the response.
     */
    public function index(): JsonResponse
    {
        $prefs = OltPreference::where('user_id', Auth::id())->get();
        $data = [];

        foreach ($prefs as $pref) {
            $data[$pref->key] = $pref->value;
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Update user preferences.
     *
     * Accepts a single key-value pair or a batch of
     * preferences. For batch updates, pass an object
     * of key-value pairs in the `batch` field.
     * Missing keys are not affected.
     */
    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'key' => 'required_without:batch|string|max:255',
            'value' => 'nullable',
            'batch' => 'required_without:key|array',
            'batch.*' => 'nullable',
        ]);

        $userId = Auth::id();

        if ($request->has('batch')) {
            foreach ($payload['batch'] as $key => $value) {
                OltPreference::updateOrCreate(
                    ['user_id' => $userId, 'key' => $key],
                    ['value' => $value],
                );
            }
        } else {
            OltPreference::updateOrCreate(
                ['user_id' => $userId, 'key' => $payload['key']],
                ['value' => $payload['value']],
            );
        }

        return response()->json(['status' => 'success']);
    }
}
