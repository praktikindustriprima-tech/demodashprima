<?php

namespace App\Services;

use App\Models\Olt;
use Illuminate\Support\Facades\Log;
use SpanishForkCity\Telnet\TelnetClient;

class OltAuth
{
    protected ?TelnetClient $client = null;

    /**
     * Create a new TelnetClient for the given OLT.
     */
    protected function createClient(Olt $olt, float $connectTimeout = 3.0, float $timeout = 10.0): TelnetClient
    {
        return new TelnetClient($olt->host, (int) $olt->port, $connectTimeout, $timeout);
    }

    /**
     * Get the initial banner/welcome message from OLT without logging in.
     */
    public function getInitialBanner(Olt $olt): string
    {
        $this->client = $this->createClient($olt, 3.0, 3.0);
        $this->client->connect();

        // Wait for the first line of data or the username prompt
        $this->client->setRegexPrompt('(Username|user|User Name):');

        $bannerLines = [];
        $timeout = microtime(true) + 2; // 2 seconds timeout to capture banner

        while (microtime(true) < $timeout) {
            $line = $this->client->getLine($matchesPrompt, false);
            if ($line === false) {
                break;
            }
            if ($matchesPrompt) {
                break;
            }
            $bannerLines[] = trim($line);
        }

        $this->disconnect();

        return implode("\n", array_filter($bannerLines));
    }

    /**
     * Connect and authenticate to the OLT.
     */
    public function connect(Olt $olt): void
    {
        try {
            Log::info('OLT connect: starting', ['host' => $olt->host, 'port' => $olt->port]);

            $this->client = $this->createClient($olt);
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

            $this->client->sendCommand(OltCommand::disablePagination());
            $this->waitFor('#');
            Log::info('OLT connect: pagination disabled');

            $this->client->sendCommand(OltCommand::enterConfigureTerminal());
            $this->waitFor('(config)#');
            Log::info('OLT connect: entered config mode');

        } catch (\Exception $e) {
            Log::error('OLT connect: failed', ['error' => $e->getMessage()]);
            $this->disconnect();
            throw new \Exception('OLT Handshake Failed: '.$e->getMessage());
        }
    }

    /**
     * Send a command and return the response.
     */
    public function sendCommand(string $command, string $prompt = '(config)#', float $timeout = 15.0): string
    {
        $this->client->sendCommand($command);
        Log::info('OLT command sent', ['command' => $command]);
        $response = $this->waitFor($prompt, $timeout);
        Log::info('OLT response received', ['length' => strlen($response)]);

        return $response;
    }

    /**
     * Wait for a specific string in the output.
     */
    public function waitFor(string $needle, float $timeout = 10.0): string
    {
        $buffer = '';
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $line = $this->client->getLine($_, false);
            if ($line !== false) {
                $buffer .= $line;
                Log::debug('OLT waitFor', ['needle' => $needle, 'line' => rtrim($line)]);
                if (str_contains($buffer, $needle)) {
                    return $buffer;
                }
            }
            usleep(50000);
        }
        throw new \Exception("Timed out waiting for: {$needle}");
    }

    /**
     * Disconnect from the OLT.
     */
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * Check if currently connected.
     */
    public function isConnected(): bool
    {
        return $this->client !== null;
    }
}
