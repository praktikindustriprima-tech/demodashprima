<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\OltHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use SpanishForkCity\Telnet\TelnetClient;

class OltService
{
    protected ?TelnetClient $client = null;

    /**
     * Get the initial banner/welcome message from OLT without logging in.
     */
    public function getInitialBanner(Olt $olt): string
    {
        $this->client = new TelnetClient($olt->host, (int) $olt->port);
        $this->client->connect();
        
        // Wait for the first line of data or the username prompt
        $this->client->setRegexPrompt('(Username|user|User Name):');
        
        $bannerLines = [];
        $timeout = microtime(true) + 2; // 2 seconds timeout to capture banner
        
        while (microtime(true) < $timeout) {
            $line = $this->client->getLine($matchesPrompt, false);
            if ($line === false) break;
            if ($matchesPrompt) break;
            $bannerLines[] = trim($line);
        }
        
        $this->disconnect();

        return implode("\n", array_filter($bannerLines));
    }

    /**
     * Connect to the OLT.
     */
    public function connect(Olt $olt): void
    {
        try {
            Log::info('OLT connect: starting', ['host' => $olt->host, 'port' => $olt->port]);

            $this->client = new TelnetClient($olt->host, (int) $olt->port, 3.0, 10.0);
            $this->client->connect();
            Log::info('OLT connect: TCP connected');

            $this->client->setDoGetRemainingData(false);

            // ZTE sends "Username:" without \n — waitPrompt() won't work.
            // Drain data until "Username:" appears, then send manually.
            $this->waitFor('Username:');
            Log::info('OLT connect: got Username prompt');
            $this->client->sendCommand($olt->username);

            $this->waitFor('Password:');
            Log::info('OLT connect: got Password prompt');
            $this->client->sendCommand($olt->getDecryptedPassword());

            // Wait for shell prompt ending with #
            $this->waitFor('#');
            Log::info('OLT connect: logged in');

            $this->client->sendCommand('terminal length 0');
            $this->waitFor('#');
            Log::info('OLT connect: pagination disabled');

            $this->client->sendCommand('con t');
            $this->waitFor('(config)#');
            Log::info('OLT connect: entered config mode');

        } catch (\Exception $e) {
            Log::error('OLT connect: failed', ['error' => $e->getMessage()]);
            $this->disconnect();
            throw new \Exception("OLT Handshake Failed: " . $e->getMessage());
        }
    }

    /**
     * Execute a command and log it to history.
     */
    public function execute(Olt $olt, string $command, string $action, ?string $targetSn = null): string
    {
        if (!$this->client) {
            $this->connect($olt);
        }

        $this->client->sendCommand($command);
        Log::info('OLT execute: command sent', ['command' => $command]);
        $response = $this->waitFor('(config)#', 15.0);
        Log::info('OLT execute: response received', ['length' => strlen($response)]);

        // Log to history
        OltHistory::create([
            'user_id' => Auth::id(),
            'olt_id' => $olt->id,
            'action' => $action,
            'target_sn' => $targetSn,
            'command_sent' => $command,
            'response_raw' => $response,
            'status' => 'success', // Should be validated based on response
        ]);

        return $response;
    }

    /**
     * Parse the 'show pon onu unconfigured' output.
     */
    public function parseUnconfiguredOnus(string $output): array
    {
        $onus = [];
        $pattern = '/(gpon-olt_\d+\/\d+\/\d+)\s+([\w\.\/A-Z\-]+)\s+([\w]+)\s+([\w]+)/';
        
        if (preg_match_all($pattern, $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $onus[] = [
                    'olt_index' => $match[1],
                    'model' => $match[2],
                    'sn' => $match[3],
                    'pw' => $match[4],
                ];
            }
        }

        return $onus;
    }

    /**
     * Parse the 'show gpon onu info' output into structured data.
     */
    public function parseOnuInfo(string $output): array
    {
        $info = [];

        $patterns = [
            'onu_type' => '/Onu\s+Type\s*:\s*(.+)/i',
            'onu_sn' => '/Onu\s+SN\s*:\s*(.+)/i',
            'password' => '/Password\s*:\s*(.+)/i',
            'state' => '/State\s*:\s*(.+)/i',
            'rx_power' => '/Rx\s+Power\s*:\s*(.+)/i',
            'tx_power' => '/Tx\s+Power\s*:\s*(.+)/i',
            'distance' => '/Distance\s*:\s*(.+)/i',
            'vendor_id' => '/Vendor\s+ID\s*:\s*(.+)/i',
            'equipment_id' => '/Equipment\s+ID\s*:\s*(.+)/i',
            'firmware_version' => '/Firmware\s+Version\s*:\s*(.+)/i',
            'serial_number' => '/Serial\s+Number\s*:\s*(.+)/i',
            'description' => '/Description\s*:\s*(.+)/i',
            'admin_state' => '/Admin\s+State\s*:\s*(.+)/i',
            'oper_state' => '/Oper\s+State\s*:\s*(.+)/i',
            'last_down_cause' => '/Last\s+Down\s+Cause\s*:\s*(.+)/i',
            'channel_count' => '/Channel\s+Count\s*:\s*(.+)/i',
            'bind_number' => '/Bind\s+Number\s*:\s*(.+)/i',
            'line_profile' => '/Line\s+Profile\s*:\s*(.+)/i',
            'service_profile' => '/Service\s+Profile\s*:\s*(.+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $match)) {
                $info[$key] = trim($match[1]);
            }
        }

        return $info;
    }


    public function waitFor(string $needle, float $timeout = 10.0): string
    {
        $buffer = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = $this->client->getLine($_, false);
            if ($line !== false) {
                $buffer .= $line;
                Log::debug('OLT waitFor', ['needle' => $needle, 'line' => rtrim($line)]);
                if (str_contains($buffer, $needle)) return $buffer;
            }
            usleep(50000);
        }
        throw new \Exception("Timed out waiting for: {$needle}");
    }
    
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }
}
