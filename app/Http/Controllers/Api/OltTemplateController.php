<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOltTemplateRequest;
use App\Http\Requests\Api\UpdateOltTemplateRequest;
use App\Http\Resources\Api\OltTemplateResource;
use App\Models\OltTemplate;
use Illuminate\Http\JsonResponse;

class OltTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => OltTemplateResource::collection(OltTemplate::all()),
        ]);
    }

    public function store(StoreOltTemplateRequest $request): JsonResponse
    {
        $template = OltTemplate::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ], 201);
    }

    public function show(OltTemplate $template): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ]);
    }

    public function update(UpdateOltTemplateRequest $request, OltTemplate $template): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $template->update($data);

        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ]);
    }

    public function destroy(OltTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(null, 204);
    }

    public function setDefault(OltTemplate $template): JsonResponse
    {
        if ($template->is_default) {
            $template->update(['is_default' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Default template removed.',
            ]);
        }

        OltTemplate::query()->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ]);
    }
}
