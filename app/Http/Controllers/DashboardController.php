<?php

namespace App\Http\Controllers;

use App\Models\AuditSession;
use App\Models\Olt;
use App\Models\OltHistory;
use App\Models\Onu;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_olts' => Olt::count(),
                'total_onus' => Onu::count(),
                'active_onus' => Onu::where('status', 'active')->count(),
                'active_sessions' => AuditSession::where('status', 'active')->count(),
                'scans_today' => OltHistory::where('action', 'Scan')
                    ->whereDate('created_at', today())->count(),
                'total_actions' => OltHistory::count(),
            ],
            'recent_activity' => OltHistory::with(['user', 'olt'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'created_at' => $h->created_at->format('d M Y H:i'),
                    'user' => $h->user?->name ?? 'N/A',
                    'olt' => $h->olt?->name ?? 'N/A',
                    'action' => $h->action,
                    'status' => $h->status,
                ]),
            'onu_breakdown' => Onu::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->get(),
        ]);
    }
}
