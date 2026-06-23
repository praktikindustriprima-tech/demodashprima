<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOltRequest;
use App\Http\Requests\Api\UpdateOltRequest;
use App\Http\Resources\Api\OltResource;
use App\Models\Olt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index(): JsonResponse
    {
        $olts = Olt::all();

        return response()->json([
            'status' => 'success',
            'data' => OltResource::collection($olts),
        ]);
    }

    public function store(StoreOltRequest $request): JsonResponse
    {
        $olt = Olt::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => new OltResource($olt),
        ], 201);
    }

    public function show(Olt $olt): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new OltResource($olt),
        ]);
    }

    public function update(UpdateOltRequest $request, Olt $olt): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $olt->update($data);

        return response()->json([
            'status' => 'success',
            'data' => new OltResource($olt),
        ]);
    }

    public function destroy(Olt $olt): JsonResponse
    {
        $olt->delete();

        return response()->json(null, 204);
    }
}
