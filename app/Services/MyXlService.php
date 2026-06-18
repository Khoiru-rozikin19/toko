<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyXlService
{
    /**
     * Get the API base URL.
     */
    protected function getBaseUrl(): string
    {
        return Setting::get('myxl_api_base_url', 'https://api.xl.co.id/api/v2');
    }

    /**
     * Get the configured headers as an array.
     */
    protected function getHeaders(): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'myXL/5.0 (Android; Build/120)',
            'X-Channel' => 'MOBILE-APP',
            'X-App-Version' => '5.0',
        ];

        $customHeadersJson = Setting::get('myxl_custom_headers');
        if ($customHeadersJson) {
            try {
                $custom = json_decode($customHeadersJson, true);
                if (is_array($custom)) {
                    return array_merge($defaultHeaders, $custom);
                }
            } catch (\Exception $e) {
                Log::error('Gagal men-decode custom headers MyXL: ' . $e->getMessage());
            }
        }

        return $defaultHeaders;
    }

    /**
     * Check if simulation mode is enabled.
     */
    public function isSimulationMode(): bool
    {
        return Setting::get('myxl_simulation_mode', '1') === '1';
    }

    /**
     * Request OTP SMS.
     */
    public function requestOtp(string $msisdn): array
    {
        // Format msisdn: ensure starts with 62
        $msisdn = $this->formatMsisdn($msisdn);

        if ($this->isSimulationMode()) {
            return [
                'success' => true,
                'message' => '[SIMULASI] OTP berhasil dikirim via SMS ke ' . $msisdn . ' (Gunakan kode: 123456)',
            ];
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . '/login/otp/request', [
                    'msisdn' => $msisdn,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'OTP berhasil dikirim ke nomor Anda.',
                    'data' => $response->json(),
                ];
            }

            $msg = $response->json('message') ?? $response->json('error') ?? 'Gagal menghubungi server XL.';
            return [
                'success' => false,
                'message' => $msg,
            ];
        } catch (\Exception $e) {
            Log::error('MyXlService Request OTP Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify OTP and return access token.
     */
    public function verifyOtp(string $msisdn, string $otp): array
    {
        $msisdn = $this->formatMsisdn($msisdn);

        if ($this->isSimulationMode()) {
            if ($otp === '123456') {
                return [
                    'success' => true,
                    'token' => 'simulated_token_' . md5($msisdn . time()),
                    'profile' => [
                        'name' => 'Budi Santoso (Simulasi)',
                        'balance' => '50000',
                        'active_until' => date('Y-m-d', strtotime('+30 days')),
                    ]
                ];
            }
            return [
                'success' => false,
                'message' => '[SIMULASI] Kode OTP salah. Gunakan kode: 123456',
            ];
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . '/login/otp/verify', [
                    'msisdn' => $msisdn,
                    'otp' => $otp,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Extract token and profile
                $token = $data['data']['token'] ?? $data['token'] ?? $data['accessToken'] ?? null;
                $profile = $data['data']['profile'] ?? $data['profile'] ?? [
                    'name' => 'Pelanggan XL',
                    'balance' => $data['data']['balance'] ?? 0,
                    'active_until' => $data['data']['active_until'] ?? null
                ];

                if (!$token) {
                    return [
                        'success' => false,
                        'message' => 'Gagal mendapatkan token akses dari response.',
                    ];
                }

                return [
                    'success' => true,
                    'token' => $token,
                    'profile' => $profile,
                ];
            }

            $msg = $response->json('message') ?? $response->json('error') ?? 'Gagal memverifikasi OTP.';
            return [
                'success' => false,
                'message' => $msg,
            ];
        } catch (\Exception $e) {
            Log::error('MyXlService Verify OTP Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch active package quotas.
     */
    public function fetchDashboard(string $token): array
    {
        if ($this->isSimulationMode()) {
            return [
                'success' => true,
                'profile' => [
                    'name' => 'Budi Santoso (Simulasi)',
                    'balance' => '50000',
                    'active_until' => date('Y-m-d', strtotime('+30 days')),
                ],
                'packages' => [
                    [
                        'id' => 'pkg_xtra_combo_1',
                        'name' => 'Xtra Combo Flex M (20GB)',
                        'quota_total' => '20.00 GB',
                        'quota_remaining' => '15.42 GB',
                        'expired_at' => date('Y-m-d H:i:s', strtotime('+15 days')),
                    ],
                    [
                        'id' => 'pkg_conference_empty',
                        'name' => 'Xtra Conference 15GB (Habis)',
                        'quota_total' => '15.00 GB',
                        'quota_remaining' => '0.00 GB',
                        'expired_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
                    ],
                    [
                        'id' => 'pkg_bonus_tiktok',
                        'name' => 'Bonus Kuota TikTok 10GB',
                        'quota_total' => '10.00 GB',
                        'quota_remaining' => '2.15 GB',
                        'expired_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                    ]
                ]
            ];
        }

        try {
            $response = Http::withHeaders(array_merge($this->getHeaders(), [
                'Authorization' => 'Bearer ' . $token,
            ]))->get($this->getBaseUrl() . '/dashboard');

            if ($response->successful()) {
                $data = $response->json();

                // Format data profile & packages dari response
                $profile = $data['data']['profile'] ?? [
                    'name' => $data['data']['name'] ?? 'Pelanggan XL',
                    'balance' => $data['data']['balance'] ?? 0,
                    'active_until' => $data['data']['active_until'] ?? null,
                ];

                $rawPackages = $data['data']['packages'] ?? $data['packages'] ?? [];
                $packages = [];

                foreach ($rawPackages as $pkg) {
                    $packages[] = [
                        'id' => $pkg['id'] ?? $pkg['packageId'] ?? '',
                        'name' => $pkg['name'] ?? $pkg['packageName'] ?? 'Paket Data',
                        'quota_total' => $pkg['quota_total'] ?? $pkg['totalQuota'] ?? '0 GB',
                        'quota_remaining' => $pkg['quota_remaining'] ?? $pkg['remainingQuota'] ?? '0 GB',
                        'expired_at' => $pkg['expired_at'] ?? $pkg['expiryDate'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'profile' => $profile,
                    'packages' => $packages,
                ];
            }

            return [
                'success' => false,
                'message' => 'Gagal mengambil data dari server XL. Token mungkin kedaluwarsa.',
            ];
        } catch (\Exception $e) {
            Log::error('MyXlService Fetch Dashboard Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Stop/unsubscribe from a package.
     */
    public function unsubscribePackage(string $token, string $packageId): array
    {
        if ($this->isSimulationMode()) {
            return [
                'success' => true,
                'message' => '[SIMULASI] Paket dengan ID ' . $packageId . ' berhasil dinonaktifkan.',
            ];
        }

        try {
            $response = Http::withHeaders(array_merge($this->getHeaders(), [
                'Authorization' => 'Bearer ' . $token,
            ]))->post($this->getBaseUrl() . '/package/unsubscribe', [
                'packageId' => $packageId,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Paket berhasil dinonaktifkan.',
                ];
            }

            $msg = $response->json('message') ?? $response->json('error') ?? 'Gagal menonaktifkan paket.';
            return [
                'success' => false,
                'message' => $msg,
            ];
        } catch (\Exception $e) {
            Log::error('MyXlService Unsubscribe Package Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Helper to format MSISDN to international (62xxx).
     */
    protected function formatMsisdn(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
