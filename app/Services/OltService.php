<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\OltHistory;
use Illuminate\Support\Facades\Auth;

class OltService
{
    public function __construct(
        protected OltAuth $auth
    ) {}

    /**
     * Get the initial banner/welcome message from OLT without logging in.
     */
    public function getInitialBanner(Olt $olt): string
    {
        return $this->auth->getInitialBanner($olt);
    }

    /**
     * Connect to the OLT.
     */
    public function connect(Olt $olt): void
    {
        $this->auth->connect($olt);
    }

    /**
     * Execute a command and log it to history.
     */
    public function execute(Olt $olt, string $command, string $action, ?string $targetSn = null): string
    {
        if (! OltCommand::isValidCommand($command)) {
            throw new \Exception('Invalid or unsafe command');
        }

        if (! $this->auth->isConnected()) {
            $this->auth->connect($olt);
        }

        $response = $this->auth->sendCommand($command);

        // Log to history
        OltHistory::create([
            'user_id' => Auth::id(),
            'olt_id' => $olt->id,
            'action' => $action,
            'target_sn' => $targetSn,
            'command_sent' => $command,
            'response_raw' => $response,
            'status' => 'success',
        ]);

        return $response;
    }

    /**
     * Parse the 'show pon onu unconfigured' output.
     */
    public function parseUnconfiguredOnus(string $output): array
    {
        return OltCommand::parseUnconfiguredOnus($output);
    }

    /**
     * Parse the 'show gpon onu info' output into structured data.
     */
    public function parseOnuInfo(string $output): array
    {
        return OltCommand::parseOnuInfo($output);
    }

    /**
     * Disconnect from the OLT.
     */
    public function disconnect(): void
    {
        $this->auth->disconnect();
    }
}
