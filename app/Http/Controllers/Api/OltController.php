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
    /**
     * List all OLT devices.
     *
     * Returns every OLT record. Passwords are always excluded
     * from the response (encrypted at rest).
     */
    public function index(): JsonResponse
    {
        $olts = Olt::all();

        return response()->json([
            'status' => 'success',
            'data' => OltResource::collection($olts),
        ]);
    }

    /**
     * Create a new OLT device record.
     *
     * Stores connection credentials. The password is automatically
     * encrypted using Crypt::encryptString() before persisting.
     */
    public function store(StoreOltRequest $request): JsonResponse
    {
        $olt = Olt::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => new OltResource($olt),
        ], 201);
    }

    /**
     * Get a single OLT device details.
     *
     * Returns the OLT record identified by the given ID.
     */
    public function show(Olt $olt): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new OltResource($olt),
        ]);
    }

    /**
     * Update an OLT device record.
     *
     * All fields are optional. If password is empty or null,
     * the existing encrypted password is preserved.
     */
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

    /**
     * Delete an OLT device record.
     *
     * Permanently removes the OLT and all associated ONUs,
     * history entries, and audit sessions.
     */
    public function destroy(Olt $olt): JsonResponse
    {
        $olt->delete();

        return response()->json(null, 204);
    }
}
