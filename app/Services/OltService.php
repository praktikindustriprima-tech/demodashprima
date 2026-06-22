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
     * Execute a command in exec mode (not config mode).
     * Exits config mode, runs the command, then re-enters config mode.
     */
    public function executeExec(Olt $olt, string $command, string $action, ?string $targetSn = null): string
    {
        if (!$this->client) {
            $this->connect($olt);
        }

        // Exit config mode to get back to exec prompt (#)
        $this->client->sendCommand('exit');
        $this->waitFor('#');

        // Send the exec command
        $this->client->sendCommand($command);
        Log::info('OLT executeExec: command sent', ['command' => $command]);

        // Wait for exec prompt — output ends with #
        $response = $this->waitFor('#', 15.0);
        Log::info('OLT executeExec: response received', ['length' => strlen($response)]);

        // Re-enter config mode for subsequent commands
        $this->client->sendCommand('con t');
        $this->waitFor('(config)#');

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
     * Parse the 'show pon onu u' output.
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
