<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\Order;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Log;

class VpsSshService
{
    /**
     * Test connection to a VPS Server configuration.
     * Returns true on success, or error string on failure.
     */
    public function testConnection(array $config)
    {
        try {
            $ssh = new SSH2($config['ip_address'], $config['ssh_port'] ?? 22, 10);
            
            if (!empty($config['ssh_private_key'])) {
                try {
                    $key = PublicKeyLoader::load($config['ssh_private_key'], $config['ssh_password'] ?? false);
                    if (!$ssh->login($config['ssh_username'] ?? 'root', $key)) {
                        return 'Login gagal dengan SSH Key.';
                    }
                } catch (\Exception $e) {
                    return 'Format SSH Private Key salah atau butuh passphrase: ' . $e->getMessage();
                }
            } else {
                if (!$ssh->login($config['ssh_username'] ?? 'root', $config['ssh_password'] ?? '')) {
                    return 'Login gagal dengan Password.';
                }
            }

            $ssh->disconnect();
            return true;
        } catch (\Exception $e) {
            return 'Gagal terhubung ke VPS: ' . $e->getMessage();
        }
    }

    /**
     * Execute a command on a VpsServer.
     */
    public function executeCommand(VpsServer $server, string $command)
    {
        try {
            $ssh = new SSH2($server->ip_address, $server->ssh_port, 15);
            
            if (!empty($server->ssh_private_key)) {
                $key = PublicKeyLoader::load($server->ssh_private_key, $server->ssh_password ?? false);
                if (!$ssh->login($server->ssh_username, $key)) {
                    throw new \Exception('SSH login failed using Private Key.');
                }
            } else {
                if (!$ssh->login($server->ssh_username, $server->ssh_password ?? '')) {
                    throw new \Exception('SSH login failed using Password.');
                }
            }

            $output = $ssh->exec($command);
            $ssh->disconnect();
            
            return trim($output);
        } catch (\Exception $e) {
            Log::error('VPS SSH Command Execution failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create VPN Account on VPS automatically for successful orders.
     */
    public function createVpnAccount(Order $order)
    {
        $product = $order->product;
        if (!$product || !$product->vps_server_id || !$product->vps_command_template) {
            return;
        }

        $server = VpsServer::find($product->vps_server_id);
        if (!$server) {
            Log::error("VPS Server not found for product: " . $product->id);
            return;
        }

        // Generate username (buyer's email or phone number + order identifier to avoid duplicate username)
        $cleanEmailOrPhone = preg_replace('/[^a-zA-Z0-9]/', '', $order->email_or_whatsapp);
        $shortId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $order->id), -4);
        $username = substr($cleanEmailOrPhone, 0, 8) . $shortId;
        $username = strtolower($username);

        // Fill template command
        // Supported variables: {username}, {duration}
        $duration = $product->duration_days ?? 30;
        $command = str_replace(
            ['{username}', '{duration}'],
            [$username, $duration],
            $product->vps_command_template
        );

        Log::info("Executing VPS SSH command for order " . $order->id . ": " . $command);

        try {
            $output = $this->executeCommand($server, $command);
            if (!empty($output)) {
                $order->vpn_config = $output;
                // Note: We don't save the order here, we let the controller do it.
            }
        } catch (\Exception $e) {
            Log::error("Failed to auto-create VPS VPN account for order " . $order->id . ": " . $e->getMessage());
        }
    }
}
