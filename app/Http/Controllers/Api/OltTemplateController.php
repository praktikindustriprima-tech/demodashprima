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
    /**
     * List all OLT connection templates.
     *
     * Returns credential presets. Passwords are not included
     * in the response (stored in plaintext).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => OltTemplateResource::collection(OltTemplate::all()),
        ]);
    }

    /**
     * Create a new OLT connection template.
     *
     * Stores a credential preset for quick-scan flows.
     * Unlike OLT records, template passwords are stored
     * in plaintext.
     */
    public function store(StoreOltTemplateRequest $request): JsonResponse
    {
        $template = OltTemplate::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ], 201);
    }

    /**
     * Get a single template details.
     */
    public function show(OltTemplate $template): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new OltTemplateResource($template),
        ]);
    }

    /**
     * Update an OLT connection template.
     *
     * All fields are optional. Empty password preserves
     * the existing value.
     */
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

    /**
     * Delete an OLT connection template.
     */
    public function destroy(OltTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle a template as the default.
     *
     * If the template is already the default, removes its
     * default status. Otherwise, clears all templates'
     * is_default flags and sets this one.
     */
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
