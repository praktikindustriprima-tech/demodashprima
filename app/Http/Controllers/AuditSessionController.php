<?php

namespace App\Http\Controllers;

use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditSessionController extends Controller
{
    /**
     * List all audit sessions for the current user.
     */
    public function index(Request $request)
    {
        $sessions = AuditSession::with('olt')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $sessions]);
        }

        return inertia('olt/SessionHistory', ['sessions' => $sessions]);
    }

    /**
     * Store a new audit session.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'olt_id' => 'required|exists:olts,id',
        ]);

        $name = $request->name ?: $this->generateSessionName();

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
            'data' => $session,
        ]);
    }

    /**
     * Save ONUs permanently to a session.
     */
    public function saveOnus(Request $request, AuditSession $session)
    {
        if ($session->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Session is not active.',
            ], 400);
        }

        $request->validate([
            'onus' => 'required|array|min:1',
            'onus.*.olt_index' => 'required|string',
            'onus.*.onu_index' => 'nullable|string',
            'onus.*.sn' => 'required|string',
            'onus.*.model' => 'required|string',
            'onus.*.pw' => 'required|string',
        ]);

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
            'data' => [
                'onu_count' => $session->onu_count,
                'session' => $session,
            ],
        ]);
    }

    /**
     * Complete a session.
     */
    public function complete(AuditSession $session)
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $session,
        ]);
    }

    /**
     * Delete/close a session.
     */
    public function destroy(AuditSession $session)
    {
        $session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session deleted',
        ]);
    }

    /**
     * Get session detail with ONUs.
     */
    public function show(AuditSession $session)
    {
        $session->load(['onus', 'olt']);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'success', 'data' => $session]);
        }

        return inertia('olt/SessionDetail', ['session' => $session]);
    }

    /**
     * Check for active session (for resume).
     */
    public function active()
    {
        $session = AuditSession::with(['olt', 'onus'])
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $session,
        ]);
    }

    /**
     * Generate auto session name: AUDIT-YYYYMMDD-XXX
     */
    private function generateSessionName(): string
    {
        $today = now()->format('Ymd');
        $count = AuditSession::whereDate('created_at', now()->today())->count() + 1;

        return sprintf('AUDIT-%s-%03d', $today, $count);
    }
}
