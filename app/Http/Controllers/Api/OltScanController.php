<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use App\Models\OltTemplate;
use App\Services\OltCommand;
use App\Services\OltService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OltScanController extends Controller
{
    public function __construct(
        protected OltService $oltService
    ) {}

    /**
     * Scan an OLT for unconfigured ONUs.
     *
     * Connects via Telnet, runs `show pon onu u`, and parses
     * the output into structured ONU data including olt_index,
     * model, serial number, and password.
     */
    public function scan(Olt $olt): JsonResponse
    {
        try {
            $output = $this->oltService->execute($olt, OltCommand::buildScanOnusCommand(), 'Scan');
            $onus = OltCommand::parseUnconfiguredOnus($output);

            return response()->json([
                'status' => 'success',
                'data' => $onus,
                'meta' => [
                    'olt_id' => $olt->id,
                    'olt_name' => $olt->name,
                    'raw' => $output,
                ],
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
     * Execute an arbitrary command on an OLT.
     *
     * Sends a command via Telnet and returns the raw output.
     * Command safety restrictions apply: max 500 chars, blocks
     * shell metacharacters (;, &&, ||, |, backticks, $()) and
     * destructive commands (rm, del, format).
     */
    public function runCommand(Request $request): JsonResponse
    {
        $request->validate([
            'olt_id' => 'required|exists:olts,id',
            'command' => 'required|string',
            'action' => 'nullable|string|max:255',
        ]);

        if (! OltCommand::isValidCommand($request->command)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid command.',
            ], 400);
        }

        /** @var Olt $olt */
        $olt = Olt::findOrFail($request->olt_id);

        try {
            $output = $this->oltService->execute($olt, $request->command, $request->action ?? 'Command');

            return response()->json([
                'status' => 'success',
                'data' => ['output' => $output],
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
     * Get detailed information about a specific ONU.
     *
     * Runs `show gpon onu detail-info` for the given olt_index
     * and returns parsed data including state, signal levels,
     * firmware version, and configuration profiles.
     */
    public function onuInfo(Request $request): JsonResponse
    {
        $request->validate([
            'olt_id' => 'required|exists:olts,id',
            'olt_index' => 'required|string',
        ]);

        /** @var Olt $olt */
        $olt = Olt::findOrFail($request->olt_id);

        try {
            $command = OltCommand::buildGetOnuInfoCommand($request->olt_index);
            $output = $this->oltService->execute($olt, $command, 'ONU Info');
            $info = OltCommand::parseOnuInfo($output);

            return response()->json([
                'status' => 'success',
                'data' => $info,
                'meta' => ['raw' => $output],
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
     * Get the OLT welcome banner.
     *
     * Captures the initial banner text displayed by the OLT
     * before the login prompt, useful for verifying connectivity
     * and device identity.
     */
    public function banner(Olt $olt): JsonResponse
    {
        try {
            $banner = $this->oltService->getInitialBanner($olt);

            return response()->json([
                'status' => 'success',
                'data' => ['banner' => $banner],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reach OLT: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick scan an OLT using inline credentials.
     *
     * Accepts connection details directly without requiring a
     * pre-existing OLT record. If an OLT with the same host
     * already exists, it reuses it. Otherwise, a new OLT record
     * named "Quick Scan ({host})" is created automatically.
     */
    public function quickScan(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'nullable|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'olt_type' => 'nullable|string|max:50',
        ]);

        $olt = Olt::where('host', $request->host)->first();

        if (! $olt) {
            $olt = new Olt([
                'name' => 'Quick Scan (' . $request->host . ')',
                'host' => $request->host,
                'port' => $request->port ?? 23,
                'username' => $request->username,
                'password' => $request->password,
                'olt_type' => $request->olt_type ?? 'ZTE',
            ]);
            $olt->save();
        }

        try {
            $output = $this->oltService->execute($olt, OltCommand::buildScanOnusCommand(), 'Scan');
            $onus = OltCommand::parseUnconfiguredOnus($output);

            return response()->json([
                'status' => 'success',
                'data' => $onus,
                'meta' => [
                    'olt_id' => $olt->id,
                    'olt_name' => $olt->name,
                    'raw' => $output,
                ],
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
}
