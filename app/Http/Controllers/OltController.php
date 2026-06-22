<?php

namespace App\Http\Controllers;

use App\Models\AuditSession;
use App\Models\Olt;
use App\Models\OltHistory;
use App\Models\OltTemplate;
use App\Services\OltCommand;
use App\Services\OltService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OltController extends Controller
{
    public function __construct(
        protected OltService $oltService
    ) {
    }

    /**
     * Show the scan page.
     */
    public function index()
    {
        return Inertia::render('olt/OnuScan', [
            'olts' => Olt::all(['id', 'name', 'host']),
            'templates' => OltTemplate::all(),
        ]);
    }

    /**
     * Show the audit session page.
     */
    public function auditSession()
    {
        return Inertia::render('olt/AuditSession', [
            'olts' => Olt::all(['id', 'name', 'host']),
            'templates' => OltTemplate::all(),
        ]);
    }

    /**
     * Show OLT settings page.
     */
    public function settings()
    {
        return Inertia::render('olt/Settings', [
            'olts' => Olt::all(),
            'templates' => OltTemplate::all(),
        ]);
    }

    /**
     * Store or update OLT configuration.
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:olts,id',
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'olt_type' => 'required|string|max:50',
        ]);

        $data = $request->only(['name', 'host', 'port', 'username', 'olt_type']);

        if ($request->password) {
            $data['password'] = $request->password;
        }

        if ($request->id) {
            $olt = Olt::find($request->id);
            $olt->update($data);
        } else {
            $olt = Olt::updateOrCreate(['host' => $request->host], $data);
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'olt_id' => $olt->id]);
        }

        return redirect()->back()->with('success', 'OLT configuration saved successfully.');
    }

    /**
     * Delete OLT configuration.
     */
    public function destroyOlt(Olt $olt)
    {
        $olt->delete();

        return redirect()->back()->with('success', 'OLT deleted.');
    }

    /**
     * Get the OLT banner/welcome message.
     */
    public function getBanner(Request $request)
    {
        $request->validate(['host' => 'required', 'port' => 'required']);

        try {
            // Temporary Olt model to use service
            $olt = new Olt(['host' => $request->host, 'port' => $request->port]);
            $banner = $this->oltService->getInitialBanner($olt);

            return response()->json([
                'status' => 'success',
                'banner' => $banner,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reach OLT: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Scan for unconfigured ONUs.
     */
    public function scan(Request $request)
    {
        $olt = null;

        if ($request->template_id) {
            $template = OltTemplate::findOrFail($request->template_id);
            $olt = Olt::firstOrNew(['host' => $template->host]);
            $olt->name = $olt->name ?: $template->name;
            $olt->host = $template->host;
            $olt->port = $template->port;
            $olt->username = $template->username;
            $olt->password = $template->password;
            $olt->olt_type = 'ZTE';
            $olt->save();
        } elseif ($request->olt_id) {
            $olt = Olt::find($request->olt_id);
        } elseif ($request->host) {
            // Quick scan using provided credentials
            $olt = Olt::where('host', $request->host)->first();

            if (!$olt) {
                $olt = new Olt([
                    'name' => 'Quick Scan (' . $request->host . ')',
                    'host' => $request->host,
                    'port' => $request->port ?? 23,
                    'username' => $request->username,
                    'password' => $request->password,
                    'olt_type' => $request->olt_type ?? 'ZTE',
                ]);
                // We don't necessarily need to save it, but OltService needs an Olt model
                // However, for history logging, it's better to save it
                $olt->save();
            } elseif ($request->password) {
                // Update password if provided in quick scan
                $olt->password = $request->password;
                $olt->save();
            }
        }

        if (!$olt) {
            return response()->json([
                'status' => 'error',
                'message' => 'OLT configuration not found or provided.',
            ], 400);
        }

        try {
            $output = $this->oltService->execute($olt, OltCommand::buildScanOnusCommand(), 'Scan');
            $onus = OltCommand::parseUnconfiguredOnus($output);

            return response()->json([
                'status' => 'success',
                'data' => $onus,
                'raw' => $output,
                'olt_id' => $olt->id,
                'olt_name' => $olt->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to OLT: ' . $e->getMessage(),
            ], 500);
        } finally {
            $this->oltService->disconnect();
        }
    }

    /**
     * Run a specific diagnostic command.
     */
    public function runCommand(Request $request)
    {
        $olt = null;

        if ($request->olt_id) {
            $olt = Olt::find($request->olt_id);
        } elseif ($request->host) {
            $olt = Olt::where('host', $request->host)->first();
        }

        if (!$olt) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active OLT connection. Please connect first.',
            ], 400);
        }

        if (!OltCommand::isValidCommand($request->command)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid command.',
            ], 400);
        }

        try {
            $output = $this->oltService->execute($olt, $request->command, $request->action);

            return response()->json([
                'status' => 'success',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Command execution failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            $this->oltService->disconnect();
        }
    }

    /**
     * Get detailed info for a specific ONU via telnet.
     */
    public function getOnuInfo(Request $request)
    {
        $request->validate([
            'olt_id' => 'required|exists:olts,id',
            'olt_index' => 'required|string',
        ]);

        $olt = Olt::find($request->olt_id);

        try {
            $command = OltCommand::buildGetOnuInfoCommand($request->olt_index);
            $output = $this->oltService->execute($olt, $command, 'ONU Info');
            $info = OltCommand::parseOnuInfo($output);

            return response()->json([
                'status' => 'success',
                'data' => $info,
                'raw' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch ONU info: ' . $e->getMessage(),
            ], 500);
        } finally {
            $this->oltService->disconnect();
        }
    }

    /**
     * Show action history page.
     */
    public function actionHistory(Request $request)
    {
        $query = OltHistory::with(['user', 'olt']);

        if ($request->filter === 'daily') {
            $query->whereDate('created_at', now()->today());
        } elseif ($request->filter === 'monthly') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        }

        $history = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('olt/ActionHistory', [
            'history' => $history,
            'filters' => $request->only(['filter']),
        ]);
    }

    /**
     * Show session history page.
     */
    public function sessionHistory()
    {
        $sessions = AuditSession::with('olt')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return Inertia::render('olt/SessionHistory', [
            'sessions' => [
                'data' => $sessions->items(),
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'links' => $sessions->links(),
                'total' => $sessions->total(),
            ]
        ]);
    }

    /**
     * Export history to CSV.
     */
    public function export(Request $request)
    {
        $query = OltHistory::with(['user', 'olt']);

        if ($request->filter === 'daily') {
            $query->whereDate('created_at', now()->today());
        } elseif ($request->filter === 'monthly') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        }

        $history = $query->latest()->get();

        $filename = 'olt_history_' . ($request->filter ?? 'all') . '_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['Date', 'User', 'OLT', 'Action', 'Target SN', 'Command', 'Status'];

        $callback = function () use ($history, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($history as $item) {
                fputcsv($file, [
                    $item->created_at,
                    $item->user?->name ?? 'N/A',
                    $item->olt?->name ?? 'N/A',
                    $item->action,
                    $item->target_sn,
                    $item->command_sent,
                    $item->status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear history records.
     */
    public function clearHistory(Request $request)
    {
        $query = OltHistory::query();

        if ($request->filter === 'daily') {
            $query->whereDate('created_at', now()->today());
        } elseif ($request->filter === 'monthly') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        }

        $deleted = $query->delete();

        return redirect()->back()->with('success', "Cleared {$deleted} history record(s).");
    }
}
