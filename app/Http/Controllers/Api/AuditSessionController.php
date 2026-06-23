<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveOnusRequest;
use App\Http\Requests\Api\StoreAuditSessionRequest;
use App\Http\Resources\Api\AuditSessionResource;
use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditSessionController extends Controller
{
    /**
     * List all audit sessions for the authenticated user.
     *
     * Paginated list of sessions with their associated OLT.
     * Supports `?per_page=` to control page size.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = AuditSession::with('olt')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => AuditSessionResource::collection($sessions),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Create a new audit session.
     *
     * Links an OLT to a named session for tracking ONU audits.
     * The session name auto-generates as AUDIT-YYYYMMDD-XXX
     * if not provided. Starts in "active" status.
     */
    public function store(StoreAuditSessionRequest $request): JsonResponse
    {
        $name = $request->name ?? $this->generateSessionName();

        $session = AuditSession::create([
            'user_id' => Auth::id(),
            'olt_id' => $request->olt_id,
            'name' => $name,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $session->load('olt');

        return response()->json([
            'status' => 'success',
            'data' => new AuditSessionResource($session),
        ], 201);
    }

    /**
     * Get audit session details with ONUs.
     *
     * Returns the session along with its permanently saved
     * ONUs, temporary saved ONUs, and associated OLT.
     * Only the session owner can view it.
     */
    public function show(AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $session->load(['onus', 'savedOnus', 'olt']);

        return response()->json([
            'status' => 'success',
            'data' => new AuditSessionResource($session),
        ]);
    }

    /**
     * Delete an audit session.
     *
     * Permanently removes the session and all associated
     * ONU records. Only the session owner can delete it.
     */
    public function destroy(AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $session->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the currently active audit session.
     *
     * Returns the most recently created session with
     * "active" status for the authenticated user.
     * Returns null data if no active session exists.
     */
    public function active(): JsonResponse
    {
        $session = AuditSession::with(['olt', 'onus', 'savedOnus'])
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $session ? new AuditSessionResource($session) : null,
        ]);
    }

    /**
     * Save ONUs permanently to an audit session.
     *
     * Persists scanned ONU data to the session's onus table.
     * Session must be owned by the authenticated user and
     * must have "active" status.
     */
    public function saveOnus(SaveOnusRequest $request, AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($session->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not active.',
            ], 400);
        }

        foreach ($request->onus as $onu) {
            AuditSessionOnu::create([
                'audit_session_id' => $session->id,
                'olt_index' => $onu['olt_index'],
                'onu_index' => $onu['onu_index'] ?? null,
                'sn' => $onu['sn'],
                'model' => $onu['model'],
                'pw' => $onu['pw'],
                'scanned_at' => now(),
            ]);
        }

        $session->update([
            'onu_count' => $session->onus()->count(),
        ]);

        $session->load('onus');

        return response()->json([
            'status' => 'success',
            'data' => new AuditSessionResource($session),
        ]);
    }

    /**
     * Mark an audit session as completed.
     *
     * Sets the session status to "completed" and records
     * the completion timestamp. Only the session owner
     * can complete it.
     */
    public function complete(AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => new AuditSessionResource($session),
        ]);
    }

    /**
     * Save ONUs temporarily to an audit session.
     *
     * Stores ONU data in the saved_onus table (survives
     * page refresh). Only inserts ONUs whose SN does not
     * already exist. Returns count of added and total records.
     */
    public function saveTemporary(Request $request, AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($session->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not active.',
            ], 400);
        }

        $request->validate([
            'onus' => 'required|array|min:1',
            'onus.*.olt_index' => 'required|string',
            'onus.*.sn' => 'required|string',
            'onus.*.model' => 'required|string',
            'onus.*.pw' => 'required|string',
        ]);

        $existingSns = $session->savedOnus()->pluck('sn')->toArray();
        $newOnus = [];

        foreach ($request->onus as $onu) {
            if (! in_array($onu['sn'], $existingSns)) {
                $newOnus[] = [
                    'audit_session_id' => $session->id,
                    'olt_index' => $onu['olt_index'],
                    'sn' => $onu['sn'],
                    'model' => $onu['model'],
                    'pw' => $onu['pw'],
                    'scanned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existingSns[] = $onu['sn'];
            }
        }

        if (! empty($newOnus)) {
            $session->savedOnus()->insert($newOnus);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'added' => count($newOnus),
                'total' => $session->savedOnus()->count(),
            ],
        ]);
    }

    /**
     * Load temporarily saved ONUs.
     *
     * Returns all ONUs currently stored in the session's
     * temporary storage (saved_onus table).
     */
    public function loadTemporary(AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $savedOnus = $session->savedOnus()->get();

        return response()->json([
            'status' => 'success',
            'data' => $savedOnus,
        ]);
    }

    /**
     * Remove a single temporary ONU by serial number.
     *
     * Deletes one ONU from the session's temporary storage
     * identified by its SN. Returns the remaining count.
     */
    public function removeOnu(Request $request, AuditSession $session, string $sn): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $session->savedOnus()->where('sn', $sn)->delete();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => $session->savedOnus()->count(),
            ],
        ]);
    }

    /**
     * Clear all temporary ONU data.
     *
     * Removes every ONU from the session's temporary
     * storage in a single operation.
     */
    public function clearTemporary(AuditSession $session): JsonResponse
    {
        if ($session->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $session->savedOnus()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Temporary data cleared.',
        ]);
    }

    private function generateSessionName(): string
    {
        $today = now()->format('Ymd');
        $count = AuditSession::whereDate('created_at', now()->today())->count() + 1;

        return sprintf('AUDIT-%s-%03d', $today, $count);
    }
}
