<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OltHistoryResource;
use App\Models\OltHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OltHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OltHistory::with(['user', 'olt']);

        if ($request->filter === 'daily') {
            $query->whereDate('created_at', now()->today());
        } elseif ($request->filter === 'monthly') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        }

        $history = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => OltHistoryResource::collection($history),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $query = OltHistory::query();

        if ($request->filter === 'daily') {
            $query->whereDate('created_at', now()->today());
        } elseif ($request->filter === 'monthly') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        }

        $deleted = $query->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Cleared {$deleted} history record(s).",
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
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
            if ($file === false) {
                return;
            }
            fputcsv($file, $columns);

            foreach ($history as $item) {
                fputcsv($file, [
                    $item->created_at,
                    $item->user->name ?? 'N/A',
                    $item->olt->name ?? 'N/A',
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
}
