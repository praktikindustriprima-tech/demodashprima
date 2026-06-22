<?php

namespace App\Services;

class OltCommand
{
    /**
     * Parse the 'show pon onu unconfigured' output.
     */
    public static function parseUnconfiguredOnus(string $output): array
    {
        $onus = [];
        // OltIndex            Model                SN                 PW
        // gpon-olt_1/3/14     F670LV9.0            ZTEGD0253352       GD0253352
        $pattern = '/^(gpon-olt_\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*$/m';

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
    public static function parseOnuInfo(string $output): array
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

    /**
     * Build a ZTE ONU configuration command.
     */
    public static function buildOnuConfigCommand(string $oltIndex, string $onuId, string $onuType = 'ZTE-F670L'): string
    {
        $ponPort = str_replace('gpon-olt_', '', $oltIndex);

        return "ont add {olt_id {$ponPort}} sn-auth {$onuId} omci ont-lineprofile_id 1 ont-serviceprofile_id 1 ontversion 1";
    }

    /**
     * Build a command to delete an ONU.
     */
    public static function buildDeleteOnuCommand(string $oltIndex, string $onuId): string
    {
        return "ont delete gpon-olt {$oltIndex} sn {$onuId}";
    }

    /**
     * Build a command to get ONU details.
     */
    public static function buildGetOnuInfoCommand(string $oltIndex): string
    {
        // Convert gpon-olt_x/x/x → gpon-onu_x/x/x:1
        $commandIndex = str_replace('gpon-olt_', 'gpon-onu_', $oltIndex);

        // Append :1 if not already present
        if (!str_contains($commandIndex, ':')) {
            $commandIndex .= ':1';
        }

        return "show gpon onu detail-info {$commandIndex}";
    }

    /**
     * Build a command to list unconfigured ONUs.
     */
    public static function buildScanOnusCommand(): string
    {
        return 'show pon onu u';
    }

    // ─── Connection / Setup Commands ───────────────────────────────

    /**
     * Disable terminal pagination (terminal length 0).
     */
    public static function disablePagination(): string
    {
        return 'terminal length 0';
    }

    /**
     * Enter configure terminal mode (con t).
     */
    public static function enterConfigureTerminal(): string
    {
        return 'con t';
    }

    /**
     * Exit configure terminal mode.
     */
    public static function exitConfigureTerminal(): string
    {
        return 'exit';
    }

    /**
     * Save running config to startup (write memory).
     */
    public static function saveConfig(): string
    {
        return 'write memory';
    }

    /**
     * Show running configuration.
     */
    public static function showRunningConfig(): string
    {
        return 'show running-config';
    }

    /**
     * Show version info.
     */
    public static function showVersion(): string
    {
        return 'show version';
    }

    /**
     * Show interface status.
     */
    public static function showInterface(string $interface): string
    {
        return "show interface {$interface}";
    }

    /**
     * Show PON port status.
     */
    public static function showPonStatus(string $ponPort): string
    {
        return "show gpon interface gpon-olt {$ponPort}";
    }

    /**
     * Show MAC address table.
     */
    public static function showMacTable(): string
    {
        return 'show mac-address-table';
    }

    /**
     * Ping a host from OLT.
     */
    public static function ping(string $host): string
    {
        return "ping {$host}";
    }

    /**
     * Reboot the OLT (dangerous).
     */
    public static function reboot(): string
    {
        return 'reboot';
    }

    /**
     * Validate command for safety (basic injection prevention).
     */
    public static function isValidCommand(string $command): bool
    {
        $forbidden = [';', '&&', '||', '|', '`', '$(', 'rm ', 'del ', 'format '];

        foreach ($forbidden as $pattern) {
            if (str_contains($command, $pattern)) {
                return false;
            }
        }

        return strlen($command) > 0 && strlen($command) <= 500;
    }
}
