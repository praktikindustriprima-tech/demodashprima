<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OltPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OltPreferenceController extends Controller
{
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
