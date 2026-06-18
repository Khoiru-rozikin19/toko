<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MyXlService
{
    protected $crypto;

    public function __construct(MyXlCryptoService $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Get a config value, falling back to Setting DB, then config default, then fallback value.
     */
    protected function cfg(string $key, ?string $default = null): ?string
    {
        $dbKey = 'myxl_' . $key;
        $dbVal = Setting::get($dbKey);
        if ($dbVal !== null && $dbVal !== '') {
            return $dbVal;
        }
        return config('services.myxl.' . $key, $default);
    }

    /**
     * Check if simulation mode is enabled.
     */
    public function isSimulationMode(): bool
    {
        return Setting::get('myxl_simulation_mode', '0') === '1';
    }

    /**
     * Get or generate the encrypted Ax-Fingerprint.
     */
    public function getAxFingerprint(): string
    {
        // Use a stable, static device profile matching sunset client's logic.
        // This ensures the fingerprint is 100% consistent between requestOtp and verifyOtp requests,
        // and eliminates file permission issues with storage/app/ax.fp on the VPS.
        $manufacturer = 'samsung';
        $model = 'SM-N935F';
        $lang = 'en';
        $resolution = '720x1540';
        $tzShort = 'GMT07:00';
        $ip = '192.169.69.69';
        $fontScale = 1.0;
        $androidRelease = '13';
        $msisdn = '6281398370564';

        $plain = "{$manufacturer}|{$model}|{$lang}|{$resolution}|{$tzShort}|{$ip}|{$fontScale}|Android {$androidRelease}|{$msisdn}";
        $axFpKey = $this->cfg('ax_fp_key', '');

        // AES-256-CBC encryption with zero IV and standard PKCS7 padding
        $iv = str_repeat("\x00", 16);
        $method = (strlen($axFpKey) === 16) ? 'AES-128-CBC' : 'AES-256-CBC';
        $encrypted = openssl_encrypt($plain, $method, $axFpKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }

    /**
     * Format timestamp with 2-digit milliseconds and colon in timezone (Java format).
     */
    protected function getJavaTimestamp($dt = null): string
    {
        $dt = $dt ?: now();
        $dt = $dt->setTimezone('Asia/Jakarta');
        $ms2 = sprintf('%02d', intval(intval($dt->format('u')) / 10000));
        return $dt->format('Y-m-d\TH:i:s.') . $ms2 . $dt->format('P');
    }

    // =========================================================================
    //  CIAM ForgeRock Authentication Flow
    // =========================================================================

    /**
     * Request OTP.
     */
    public function requestOtp(string $phone): array
    {
        $msisdn = $this->formatMsisdn($phone);

        if ($this->isSimulationMode()) {
            return [
                'success' => true,
                'message' => '[SIMULASI] OTP berhasil dikirim via SMS ke ' . $msisdn . ' (Gunakan kode: 123456)',
                'subscriber_id' => 'simulated_sub_id_' . md5($msisdn),
            ];
        }

        $baseCiam = rtrim($this->cfg('base_ciam_url', 'https://gede.ciam.xlaxiata.co.id'), '/');
        $url = $baseCiam . '/realms/xl-ciam/auth/otp';

        $basicAuth = $this->cfg('basic_auth', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $axFp = $this->getAxFingerprint();

        $axRequestAt = $this->getJavaTimestamp();
        $axRequestId = (string) Str::uuid();
        $axDeviceId = md5($axFp);

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Basic ' . $basicAuth,
                    'Ax-Device-Id' => $axDeviceId,
                    'Ax-Fingerprint' => $axFp,
                    'Ax-Request-At' => $axRequestAt,
                    'Ax-Request-Device' => 'samsung',
                    'Ax-Request-Device-Model' => 'SM-N935F',
                    'Ax-Request-Id' => $axRequestId,
                    'Ax-Substype' => 'PREPAID',
                    'Content-Type' => 'application/json',
                    'Host' => str_replace('https://', '', $baseCiam),
                    'User-Agent' => $ua,
                ])
                ->get($url, [
                    'contact' => $msisdn,
                    'contactType' => 'SMS',
                    'alternateContact' => 'false'
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['subscriber_id'])) {
                    return [
                        'success' => true,
                        'subscriber_id' => $body['subscriber_id'],
                        'message' => 'OTP berhasil dikirim ke nomor ' . $msisdn . '. Silakan cek SMS Anda.'
                    ];
                }
                return [
                    'success' => false,
                    'message' => $body['error'] ?? 'Subscriber ID tidak ditemukan dalam response.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Gagal request OTP: ' . ($response->json('error') ?? $response->body())
            ];
        } catch (\Exception $e) {
            Log::error('MyXL Request OTP Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi error: ' . $e->getMessage()];
        }
    }

    /**
     * Verify OTP SMS and return tokens.
     */
    public function verifyOtp(string $phone, string $otp): array
    {
        $msisdn = $this->formatMsisdn($phone);

        if ($this->isSimulationMode()) {
            if ($otp === '123456') {
                return [
                    'success' => true,
                    'tokens' => [
                        'access_token' => 'sim_access_token_' . md5($msisdn),
                        'id_token' => 'sim_id_token_' . md5($msisdn),
                        'refresh_token' => 'sim_refresh_token_' . md5($msisdn),
                    ],
                    'profile' => [
                        'subscriber_id' => 'simulated_sub_id_' . md5($msisdn),
                        'subscription_type' => 'PREPAID',
                    ]
                ];
            }
            return ['success' => false, 'message' => '[SIMULASI] OTP salah. Gunakan kode: 123456'];
        }

        $baseCiam = rtrim($this->cfg('base_ciam_url', 'https://gede.ciam.xlaxiata.co.id'), '/');
        $url = $baseCiam . '/realms/xl-ciam/protocol/openid-connect/token';

        $basicAuth = $this->cfg('basic_auth', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $axFp = $this->getAxFingerprint();
        $axApiSigKey = $this->cfg('ax_api_sig_key', '');
        $apiKey = $this->cfg('api_key', '');

        $now = now()->setTimezone('Asia/Jakarta');
        // Signature needs timestamp in GMT+7 format without colon in timezone: Y-m-d\TH:i:s.vO
        $tsForSign = $now->format('Y-m-d\TH:i:s.vO'); 
        $tsHeader = $now->subMinutes(5)->format('Y-m-d\TH:i:s.vO');

        $signature = $this->crypto->makeAxApiSignature($tsForSign, $msisdn, $otp, 'SMS', $axApiSigKey);
        $axDeviceId = md5($axFp);

        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->withHeaders([
                    'Authorization' => 'Basic ' . $basicAuth,
                    'Ax-Api-Signature' => $signature,
                    'Ax-Device-Id' => $axDeviceId,
                    'Ax-Fingerprint' => $axFp,
                    'Ax-Request-At' => $tsHeader,
                    'Ax-Request-Device' => 'samsung',
                    'Ax-Request-Device-Model' => 'SM-N935F',
                    'Ax-Request-Id' => (string) Str::uuid(),
                    'Ax-Substype' => 'PREPAID',
                    'User-Agent' => $ua,
                ])
                ->post($url, [
                    'contactType' => 'SMS',
                    'code' => $otp,
                    'grant_type' => 'password',
                    'contact' => $msisdn,
                    'scope' => 'openid'
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['id_token'])) {
                    // Fetch profile to get subscriber ID
                    $tokens = [
                        'access_token' => $body['access_token'],
                        'id_token' => $body['id_token'],
                        'refresh_token' => $body['refresh_token'],
                    ];
                    $profileRes = $this->getProfile($tokens);
                    if ($profileRes && isset($profileRes['profile'])) {
                        return [
                            'success' => true,
                            'tokens' => $tokens,
                            'profile' => [
                                'subscriber_id' => $profileRes['profile']['subscriber_id'],
                                'subscription_type' => $profileRes['profile']['subscription_type'],
                            ]
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Verifikasi OTP gagal (Gagal mengambil profil): ' . json_encode($profileRes)
                        ];
                    }
                }
            }

            return [
                'success' => false,
                'message' => 'Verifikasi OTP gagal: ' . ($response->json('error_description') ?? $response->json('error') ?? $response->body())
            ];
        } catch (\Exception $e) {
            Log::error('MyXL Verify OTP Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi error: ' . $e->getMessage()];
        }
    }

    /**
     * Refresh active token using refresh token.
     */
    public function refreshToken(string $refreshToken): ?array
    {
        if ($this->isSimulationMode()) {
            return [
                'access_token' => 'sim_access_token_' . time(),
                'id_token' => 'sim_id_token_' . time(),
                'refresh_token' => $refreshToken,
            ];
        }

        $baseCiam = rtrim($this->cfg('base_ciam_url', 'https://gede.ciam.xlaxiata.co.id'), '/');
        $url = $baseCiam . '/realms/xl-ciam/protocol/openid-connect/token';

        $basicAuth = $this->cfg('basic_auth', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $axFp = $this->getAxFingerprint();

        $now = now()->setTimezone('Asia/Jakarta');
        $axRequestAt = $now->format('Y-m-d\TH:i:s.vO');
        $axDeviceId = md5($axFp);

        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->withHeaders([
                    'host' => str_replace('https://', '', $baseCiam),
                    'ax-request-at' => $axRequestAt,
                    'ax-device-id' => $axDeviceId,
                    'ax-request-id' => (string) Str::uuid(),
                    'ax-request-device' => 'samsung',
                    'ax-request-device-model' => 'SM-N935F',
                    'ax-fingerprint' => $axFp,
                    'authorization' => 'Basic ' . $basicAuth,
                    'user-agent' => $ua,
                    'ax-substype' => 'PREPAID',
                ])
                ->post($url, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['id_token'])) {
                    return [
                        'access_token' => $body['access_token'],
                        'id_token' => $body['id_token'],
                        'refresh_token' => $body['refresh_token'],
                    ];
                }
            }
            return null;
        } catch (\Exception $e) {
            Log::error('MyXL Refresh Token Error: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    //  Core API Sender
    // =========================================================================

    /**
     * Send signed and encrypted request to MyXL API.
     */
    protected function sendApiRequest(string $path, array $payload, array $tokens, string $method = 'POST'): array
    {
        $apiKey = $this->cfg('api_key', '');
        $xdataKey = $this->cfg('xdata_key', '');
        $xApiBaseSecret = $this->cfg('x_api_base_secret', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $baseApiUrl = rtrim($this->cfg('base_api_url', 'https://api.myxl.xlaxiata.co.id'), '/');

        $xtime = (int) (microtime(true) * 1000);
        $sigTimeSec = (int) ($xtime / 1000);

        $plainBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $xdata = $this->crypto->encryptXData($plainBody, $xtime, $xdataKey);
        $xSig = $this->crypto->makeXSignature($tokens['id_token'], $method, $path, $sigTimeSec, $xApiBaseSecret);

        $axRequestAt = $this->getJavaTimestamp();

        $headers = [
            'host' => str_replace('https://', '', $baseApiUrl),
            'content-type' => 'application/json; charset=utf-8',
            'user-agent' => $ua,
            'x-api-key' => $apiKey,
            'authorization' => 'Bearer ' . $tokens['id_token'],
            'x-hv' => 'v3',
            'x-signature-time' => (string) $sigTimeSec,
            'x-signature' => $xSig,
            'x-request-id' => (string) Str::uuid(),
            'x-request-at' => $axRequestAt,
            'x-version-app' => '8.9.0',
        ];

        $url = $baseApiUrl . '/' . $path;
        $body = [
            'xdata' => $xdata,
            'xtime' => $xtime
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->withBody(json_encode($body), 'application/json')
                ->send($method, $url);

            if ($response->successful()) {
                $resBody = $response->json();
                if (isset($resBody['xdata'])) {
                    $decrypted = $this->crypto->decryptXData($resBody['xdata'], (int) $resBody['xtime'], $xdataKey);
                    return json_decode($decrypted, true) ?? [];
                }
                return $resBody;
            }

            return [
                'status' => 'FAILED',
                'message' => 'API error: ' . $response->status() . ' - ' . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('MyXL API Call Exception: ' . $e->getMessage());
            return ['status' => 'FAILED', 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    //  Account, Profile & Balance Services
    // =========================================================================

    /**
     * Get active profile details.
     */
    public function getProfile(array $tokens): ?array
    {
        if ($this->isSimulationMode()) {
            return [
                'profile' => [
                    'subscriber_id' => 'simulated_sub_id_12345',
                    'subscription_type' => 'PREPAID',
                    'name' => 'Budi Santoso (Simulasi)',
                    'msisdn' => '6287860356425',
                ]
            ];
        }

        $res = $this->sendApiRequest('api/v8/profile', [
            'access_token' => $tokens['access_token'],
            'app_version' => '8.9.0',
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);

        return $res['data'] ?? null;
    }

    /**
     * Get sisa pulsa / balance.
     */
    public function getBalance(array $tokens): ?array
    {
        if ($this->isSimulationMode()) {
            return [
                'remaining' => '50000',
                'expired_at' => time() + 30 * 86400, // 30 days expiry
            ];
        }

        $res = $this->sendApiRequest('api/v8/packages/balance-and-credit', [
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);

        return $res['data']['balance'] ?? null;
    }

    /**
     * Get points, tier loyalty.
     */
    public function getTieringInfo(array $tokens): array
    {
        if ($this->isSimulationMode()) {
            return [
                'tier' => 'Platinum',
                'current_point' => 1250,
            ];
        }

        $res = $this->sendApiRequest('gamification/api/v8/loyalties/tiering/info', [
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);

        return $res['data'] ?? [];
    }

    // =========================================================================
    //  Store, Families & Package Details
    // =========================================================================

    /**
     * Get categories / packages list inside store.
     */
    public function getFamilies(array $tokens, string $packageCategoryCode = 'NEW_HOT_PROMO'): array
    {
        if ($this->isSimulationMode()) {
            return [
                'list' => [
                    [
                        'package_family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
                        'name' => 'Xtra Combo Flex M (20GB)',
                        'icon_url' => '',
                        'price' => 50000,
                    ],
                    [
                        'package_family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50c0',
                        'name' => 'Akrab L (100GB Shared)',
                        'icon_url' => '',
                        'price' => 150000,
                    ]
                ]
            ];
        }

        $res = $this->sendApiRequest('api/v8/xl-stores/families', [
            'migration_type' => '',
            'is_enterprise' => false,
            'is_shareable' => false,
            'package_category_code' => $packageCategoryCode,
            'with_icon_url' => true,
            'is_migration' => false,
            'lang' => 'en'
        ], $tokens);

        return $res['data'] ?? [];
    }

    /**
     * Get family code details options.
     */
    public function getFamily(array $tokens, string $familyCode, ?bool $isEnterprise = null, ?string $migrationType = null): ?array
    {
        if ($this->isSimulationMode()) {
            return [
                'package_family' => [
                    'name' => 'Xtra Combo Flex M (Simulasi)',
                    'package_family_code' => $familyCode,
                ],
                'package_variants' => [
                    [
                        'name' => 'Flex M Promo',
                        'package_variant_code' => 'variant_m_1',
                        'package_options' => [
                            [
                                'order' => 1,
                                'name' => 'Flex M 20GB',
                                'price' => 45000,
                                'package_option_code' => 'option_flex_m_20gb',
                            ]
                        ]
                    ]
                ]
            ];
        }

        $isEnterpriseList = $isEnterprise !== null ? [$isEnterprise] : [false, true];
        $migrationTypeList = $migrationType !== null ? [$migrationType] : ['NONE', 'PRE_TO_PRIOH', 'PRIOH_TO_PRIO', 'PRIO_TO_PRIOH'];

        foreach ($migrationTypeList as $mt) {
            foreach ($isEnterpriseList as $ie) {
                $res = $this->sendApiRequest('api/v8/xl-stores/options/list', [
                    'is_show_tagging_tab' => true,
                    'is_dedicated_event' => true,
                    'is_transaction_routine' => false,
                    'migration_type' => $mt,
                    'package_family_code' => $familyCode,
                    'is_autobuy' => false,
                    'is_enterprise' => $ie,
                    'is_pdlp' => true,
                    'referral_code' => '',
                    'is_migration' => false,
                    'lang' => 'en'
                ], $tokens);

                if (isset($res['status']) && $res['status'] === 'SUCCESS' && !empty($res['data']['package_family']['name'])) {
                    return $res['data'];
                }
            }
        }

        return null;
    }

    /**
     * Get package details by Option Code.
     */
    public function getPackage(array $tokens, string $packageOptionCode, string $packageFamilyCode = '', string $packageVariantCode = ''): ?array
    {
        if ($this->isSimulationMode()) {
            return [
                'package_option' => [
                    'package_option_code' => $packageOptionCode,
                    'name' => 'Flex M 20GB (Simulated)',
                    'price' => 45000,
                ],
                'token_confirmation' => 'simulated_conf_token_abc123'
            ];
        }

        $res = $this->sendApiRequest('api/v8/xl-stores/options/detail', [
            'is_transaction_routine' => false,
            'migration_type' => 'NONE',
            'package_family_code' => $packageFamilyCode,
            'family_role_hub' => '',
            'is_autobuy' => false,
            'is_enterprise' => false,
            'is_shareable' => false,
            'is_migration' => false,
            'lang' => 'en',
            'package_option_code' => $packageOptionCode,
            'is_upsell_pdp' => false,
            'package_variant_code' => $packageVariantCode
        ], $tokens);

        return $res['data'] ?? null;
    }

    /**
     * Fetch package details relative to a family tree.
     */
    public function getPackageDetails(array $tokens, string $familyCode, string $variantCode, int $optionOrder, ?bool $isEnterprise = null, ?string $migrationType = null): ?array
    {
        $familyData = $this->getFamily($tokens, $familyCode, $isEnterprise, $migrationType);
        if (!$familyData) {
            return null;
        }

        $variants = $familyData['package_variants'] ?? [];
        $optionCode = null;
        foreach ($variants as $variant) {
            if ($variant['package_variant_code'] === $variantCode) {
                foreach ($variant['package_options'] as $option) {
                    if ((int) $option['order'] === $optionOrder) {
                        $optionCode = $option['package_option_code'];
                        break 2;
                    }
                }
            }
        }

        if (!$optionCode) {
            return null;
        }

        return $this->getPackage($tokens, $optionCode, $familyCode, $variantCode);
    }

    /**
     * Intercept page payload setup for some promo packages.
     */
    public function interceptPage(array $tokens, string $optionCode, bool $isEnterprise = false): void
    {
        if ($this->isSimulationMode()) {
            return;
        }

        $this->sendApiRequest('misc/api/v8/utility/intercept-page', [
            'is_enterprise' => $isEnterprise,
            'lang' => 'en',
            'package_option_code' => $optionCode
        ], $tokens);
    }

    // =========================================================================
    //  Purchase & Settlements (Balance & QRIS & Decoy)
    // =========================================================================

    /**
     * Initiate payment methods list.
     */
    protected function getPaymentMethods(array $tokens, string $itemCode, string $tokenConfirmation): ?array
    {
        $res = $this->sendApiRequest('payments/api/v8/payment-methods-option', [
            'payment_type' => 'PURCHASE',
            'is_enterprise' => false,
            'payment_target' => $itemCode,
            'lang' => 'en',
            'is_referral' => false,
            'token_confirmation' => $tokenConfirmation
        ], $tokens);

        return $res['data'] ?? null;
    }

    /**
     * Purchase package using Balance.
     */
    public function settlementBalance(array $tokens, array $paymentItems, int $amount, string $paymentFor = 'BUY_PACKAGE', string $topupNumber = '', string $stageToken = ''): array
    {
        if ($this->isSimulationMode()) {
            return ['status' => 'SUCCESS', 'message' => '[SIMULASI] Paket berhasil dibeli menggunakan pulsa.'];
        }

        $firstItem = $paymentItems[0];
        $this->interceptPage($tokens, $firstItem['item_code']);

        $payMethods = $this->getPaymentMethods($tokens, $firstItem['item_code'], $firstItem['token_confirmation']);
        if (!$payMethods) {
            return ['status' => 'FAILED', 'message' => 'Gagal mengambil metode pembayaran.'];
        }

        $tokenPayment = $payMethods['token_payment'];
        $tsToSign = $payMethods['timestamp'];

        $path = 'payments/api/v8/settlement-multipayment';
        $payload = [
            'total_discount' => 0,
            'is_enterprise' => false,
            'payment_token' => '',
            'token_payment' => $tokenPayment,
            'activated_autobuy_code' => '',
            'cc_payment_type' => '',
            'is_myxl_wallet' => false,
            'pin' => '',
            'ewallet_promo_id' => '',
            'members' => [],
            'total_fee' => 0,
            'fingerprint' => '',
            'autobuy_threshold_setting' => ['label' => '', 'type' => '', 'value' => 0],
            'is_use_point' => false,
            'lang' => 'en',
            'payment_method' => 'BALANCE',
            'timestamp' => time(),
            'points_gained' => 0,
            'can_trigger_rating' => false,
            'akrab_members' => [],
            'akrab_parent_alias' => '',
            'referral_unique_code' => '',
            'coupon' => '',
            'payment_for' => $paymentFor,
            'with_upsell' => false,
            'topup_number' => $topupNumber,
            'stage_token' => $stageToken,
            'authentication_id' => '',
            'encrypted_payment_token' => $this->crypto->encryptCircleMsisdn('', $this->cfg('encrypted_field_key', '')), // wait, build_encrypted_field mock
            'token' => '',
            'token_confirmation' => '',
            'access_token' => $tokens['access_token'],
            'wallet_number' => '',
            'encrypted_authentication_id' => $this->crypto->encryptCircleMsisdn('', $this->cfg('encrypted_field_key', '')),
            'additional_data' => [
                'original_price' => $firstItem['item_price'],
                'is_spend_limit_temporary' => false,
                'migration_type' => '',
                'akrab_m2m_group_id' => 'false',
                'spend_limit_amount' => 0,
                'is_spend_limit' => false,
                'mission_id' => '',
                'tax' => 0,
                'quota_bonus' => 0,
                'cashtag' => '',
                'is_family_plan' => false,
                'combo_details' => [],
                'is_switch_plan' => false,
                'discount_recurring' => 0,
                'is_akrab_m2m' => false,
                'balance_type' => 'PREPAID_BALANCE',
                'has_bonus' => false,
                'discount_promo' => 0
            ],
            'total_amount' => $amount,
            'is_using_autobuy' => false,
            'items' => $paymentItems
        ];

        // Custom signing logic for payments
        $apiKey = $this->cfg('api_key', '');
        $xdataKey = $this->cfg('xdata_key', '');
        $xApiBaseSecret = $this->cfg('x_api_base_secret', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $baseApiUrl = rtrim($this->cfg('base_api_url', 'https://api.myxl.xlaxiata.co.id'), '/');

        // Target code calculation
        $paymentTargets = implode(';', array_column($paymentItems, 'item_code'));

        $xtime = (int) (microtime(true) * 1000);
        $sigTimeSec = (int) ($xtime / 1000);

        $payload['timestamp'] = $tsToSign;
        $plainBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $xdata = $this->crypto->encryptXData($plainBody, $xtime, $xdataKey);

        $xSig = $this->crypto->makeXSignaturePayment(
            $tokens['access_token'],
            $tsToSign,
            $paymentTargets,
            $tokenPayment,
            'BALANCE',
            $paymentFor,
            $path,
            $xApiBaseSecret
        );

        $axRequestAt = $this->getJavaTimestamp();

        $headers = [
            'host' => str_replace('https://', '', $baseApiUrl),
            'content-type' => 'application/json; charset=utf-8',
            'user-agent' => $ua,
            'x-api-key' => $apiKey,
            'authorization' => 'Bearer ' . $tokens['id_token'],
            'x-hv' => 'v3',
            'x-signature-time' => (string) $sigTimeSec,
            'x-signature' => $xSig,
            'x-request-id' => (string) Str::uuid(),
            'x-request-at' => $axRequestAt,
            'x-version-app' => '8.9.0',
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->withBody(json_encode(['xdata' => $xdata, 'xtime' => $xtime]), 'application/json')
                ->post($baseApiUrl . '/' . $path);

            if ($response->successful()) {
                $resBody = $response->json();
                if (isset($resBody['xdata'])) {
                    $decrypted = $this->crypto->decryptXData($resBody['xdata'], (int) $resBody['xtime'], $xdataKey);
                    return json_decode($decrypted, true) ?? [];
                }
                return $resBody;
            }
            return ['status' => 'FAILED', 'message' => 'HTTP status: ' . $response->status()];
        } catch (\Exception $e) {
            return ['status' => 'FAILED', 'message' => $e->getMessage()];
        }
    }

    /**
     * Purchase package using QRIS (Returns transaction code).
     */
    public function settlementQris(array $tokens, array $paymentItems, int $amount, string $paymentFor = 'BUY_PACKAGE', string $topupNumber = '', string $stageToken = ''): ?string
    {
        if ($this->isSimulationMode()) {
            return 'sim_trx_code_' . time();
        }

        $firstItem = $paymentItems[0];
        $this->interceptPage($tokens, $firstItem['item_code']);

        $payMethods = $this->getPaymentMethods($tokens, $firstItem['item_code'], $firstItem['token_confirmation']);
        if (!$payMethods) {
            return null;
        }

        $tokenPayment = $payMethods['token_payment'];
        $tsToSign = $payMethods['timestamp'];

        $path = 'payments/api/v8/settlement-multipayment/qris';
        $payload = [
            'akrab' => ['akrab_members' => [], 'akrab_parent_alias' => '', 'members' => []],
            'can_trigger_rating' => false,
            'total_discount' => 0,
            'coupon' => '',
            'payment_for' => $paymentFor,
            'topup_number' => $topupNumber,
            'stage_token' => $stageToken,
            'is_enterprise' => false,
            'autobuy' => [
                'is_using_autobuy' => false,
                'activated_autobuy_code' => '',
                'autobuy_threshold_setting' => ['label' => '', 'type' => '', 'value' => 0]
            ],
            'access_token' => $tokens['access_token'],
            'is_myxl_wallet' => false,
            'additional_data' => [
                'original_price' => $firstItem['item_price'],
                'is_spend_limit_temporary' => false,
                'migration_type' => '',
                'spend_limit_amount' => 0,
                'is_spend_limit' => false,
                'tax' => 0,
                'benefit_type' => '',
                'quota_bonus' => 0,
                'cashtag' => '',
                'is_family_plan' => false,
                'combo_details' => [],
                'is_switch_plan' => false,
                'discount_recurring' => 0,
                'has_bonus' => false,
                'discount_promo' => 0
            ],
            'total_amount' => $amount,
            'total_fee' => 0,
            'is_use_point' => false,
            'lang' => 'en',
            'items' => $paymentItems,
            'verification_token' => $tokenPayment,
            'payment_method' => 'QRIS',
            'timestamp' => time(),
        ];

        // Custom signing logic for payments
        $apiKey = $this->cfg('api_key', '');
        $xdataKey = $this->cfg('xdata_key', '');
        $xApiBaseSecret = $this->cfg('x_api_base_secret', '');
        $ua = $this->cfg('ua', 'myXL / 8.9.0(1202); com.android.vending');
        $baseApiUrl = rtrim($this->cfg('base_api_url', 'https://api.myxl.xlaxiata.co.id'), '/');

        // Target code calculation
        $paymentTargets = implode(';', array_column($paymentItems, 'item_code'));

        $xtime = (int) (microtime(true) * 1000);
        $sigTimeSec = (int) ($xtime / 1000);

        $payload['timestamp'] = $tsToSign;
        $plainBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $xdata = $this->crypto->encryptXData($plainBody, $xtime, $xdataKey);

        $xSig = $this->crypto->makeXSignaturePayment(
            $tokens['access_token'],
            $tsToSign,
            $paymentTargets,
            $tokenPayment,
            'QRIS',
            $paymentFor,
            $path,
            $xApiBaseSecret
        );

        $axRequestAt = $this->getJavaTimestamp();

        $headers = [
            'host' => str_replace('https://', '', $baseApiUrl),
            'content-type' => 'application/json; charset=utf-8',
            'user-agent' => $ua,
            'x-api-key' => $apiKey,
            'authorization' => 'Bearer ' . $tokens['id_token'],
            'x-hv' => 'v3',
            'x-signature-time' => (string) $sigTimeSec,
            'x-signature' => $xSig,
            'x-request-id' => (string) Str::uuid(),
            'x-request-at' => $axRequestAt,
            'x-version-app' => '8.9.0',
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->withBody(json_encode(['xdata' => $xdata, 'xtime' => $xtime]), 'application/json')
                ->post($baseApiUrl . '/' . $path);

            if ($response->successful()) {
                $resBody = $response->json();
                if (isset($resBody['xdata'])) {
                    $decrypted = $this->crypto->decryptXData($resBody['xdata'], (int) $resBody['xtime'], $xdataKey);
                    $data = json_decode($decrypted, true) ?? [];
                    if (isset($data['status']) && $data['status'] === 'SUCCESS') {
                        return $data['data']['transaction_code'] ?? null;
                    }
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get QRIS string payload (returns the payload parsed by MyXL).
     */
    public function getQrisCode(array $tokens, string $transactionId): ?string
    {
        if ($this->isSimulationMode()) {
            // Return standard dummy QRIS payload (valid visual mapping)
            return '00020101021226300016COM.NOBUBANK.WWW01189360050300000876540214123456789012340303UMI51440014ID.CO.QRIS.WWW02150000000000000000303UMI5204531153033605802ID5903RZK6007BANDUNG61054011562070703A016304ABCD';
        }

        $res = $this->sendApiRequest('payments/api/v8/pending-detail', [
            'transaction_id' => $transactionId,
            'is_enterprise' => false,
            'lang' => 'en',
            'status' => ''
        ], $tokens);

        return $res['data']['qr_code'] ?? null;
    }

    // =========================================================================
    //  Akrab Family Organizer Services
    // =========================================================================

    /**
     * Get family plan details.
     */
    public function getFamilyData(array $tokens): array
    {
        if ($this->isSimulationMode()) {
            return [
                'status' => 'SUCCESS',
                'data' => [
                    'member_info' => [
                        'plan_type' => 'Akrab M 25GB',
                        'parent_msisdn' => '6287860356425',
                        'total_quota' => 26843545600, // 25 GB in bytes
                        'remaining_quota' => 10737418240, // 10 GB in bytes
                        'end_date' => time() + 15 * 86400,
                        'members' => [
                            [
                                'msisdn' => '6287860356426',
                                'alias' => 'Istri',
                                'slot_id' => 'slot_1',
                                'family_member_id' => 'member_id_1',
                                'member_type' => 'CHILD',
                                'add_chances' => 1,
                                'total_add_chances' => 1,
                                'usage' => [
                                    'quota_allocated' => 5368709120, // 5 GB
                                    'quota_used' => 2147483648, // 2 GB
                                    'quota_expired_at' => time() + 15 * 86400,
                                ]
                            ],
                            [
                                'msisdn' => '',
                                'alias' => '',
                                'slot_id' => 'slot_2',
                                'family_member_id' => 'member_id_2',
                                'member_type' => 'CHILD',
                                'add_chances' => 1,
                                'total_add_chances' => 1,
                                'usage' => [
                                    'quota_allocated' => 0,
                                    'quota_used' => 0,
                                    'quota_expired_at' => 0,
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $this->sendApiRequest('family/api/v8/family-plan/data', [
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);
    }

    /**
     * Add or change family slot member.
     */
    public function changeMember(array $tokens, string $parentAlias, string $childAlias, string $slotId, string $familyMemberId, string $targetMsisdn): array
    {
        if ($this->isSimulationMode()) {
            return ['status' => 'SUCCESS', 'message' => 'Anggota berhasil ditambahkan ke slot Akrab.'];
        }

        return $this->sendApiRequest('family/api/v8/family-plan/member/change', [
            'is_enterprise' => false,
            'parent_alias' => $parentAlias,
            'child_alias' => $childAlias,
            'slot_id' => $slotId,
            'family_member_id' => $familyMemberId,
            'target_msisdn' => $targetMsisdn,
            'lang' => 'en'
        ], $tokens);
    }

    /**
     * Delete family slot member.
     */
    public function removeMember(array $tokens, string $familyMemberId): array
    {
        if ($this->isSimulationMode()) {
            return ['status' => 'SUCCESS', 'message' => 'Anggota berhasil dihapus dari slot Akrab.'];
        }

        return $this->sendApiRequest('family/api/v8/family-plan/member/remove', [
            'family_member_id' => $familyMemberId,
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);
    }

    /**
     * Set quota limit for a family member.
     */
    public function setQuotaLimit(array $tokens, int $originalAllocationByte, int $newAllocationByte, string $familyMemberId): array
    {
        if ($this->isSimulationMode()) {
            return ['status' => 'SUCCESS', 'message' => 'Limit kuota anggota berhasil diperbarui.'];
        }

        return $this->sendApiRequest('family/api/v8/family-plan/quota/set', [
            'original_allocation_byte' => $originalAllocationByte,
            'new_allocation_byte' => $newAllocationByte,
            'family_member_id' => $familyMemberId,
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);
    }

    // =========================================================================
    //  Transaction History, Unsubscribe & Validation Utils
    // =========================================================================

    /**
     * Get transaction history.
     */
    public function getTransactionHistory(array $tokens): array
    {
        if ($this->isSimulationMode()) {
            return [
                'list' => [
                    [
                        'title' => 'Xtra Combo Flex M (20GB)',
                        'price' => 'IDR 50000',
                        'raw_price' => 50000,
                        'formated_date' => now()->subDays(2)->format('d F Y | H:i') . ' WIB',
                        'payment_method_label' => 'BALANCE',
                        'payment_status' => 'SUCCESS',
                        'status' => 'SUCCESS',
                        'timestamp' => time() - 2 * 86400,
                    ],
                    [
                        'title' => 'Pulsa Transfer Rp100.000',
                        'price' => 'IDR 100000',
                        'raw_price' => 100000,
                        'formated_date' => now()->subDays(5)->format('d F Y | H:i') . ' WIB',
                        'payment_method_label' => 'QRIS',
                        'payment_status' => 'SUCCESS',
                        'status' => 'SUCCESS',
                        'timestamp' => time() - 5 * 86400,
                    ]
                ]
            ];
        }

        $res = $this->sendApiRequest('payments/api/v8/transaction-history', [
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);

        return $res['data'] ?? [];
    }

    /**
     * Validate MSISDN.
     */
    public function validateMsisdn(array $tokens, string $msisdn): array
    {
        if ($this->isSimulationMode()) {
            return [
                'status' => 'SUCCESS',
                'data' => [
                    'family_plan_role' => 'NO_ROLE',
                    'msisdn' => $msisdn,
                ]
            ];
        }

        return $this->sendApiRequest('family/api/v8/family-plan/msisdn/validate', [
            'target_msisdn' => $msisdn,
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);
    }

    /**
     * Unsubscribe package.
     */
    public function unsubscribe(array $tokens, string $quotaCode, string $productDomain, string $productSubscriptionType): array
    {
        if ($this->isSimulationMode()) {
            return ['status' => 'SUCCESS', 'message' => '[SIMULASI] Paket berhasil dihentikan.'];
        }

        $res = $this->sendApiRequest('api/v8/packages/unsubscribe', [
            'product_subscription_type' => $productSubscriptionType,
            'quota_code' => $quotaCode,
            'product_domain' => $productDomain,
            'is_enterprise' => false,
            'unsubscribe_reason_code' => '',
            'lang' => 'en',
            'family_member_id' => ''
        ], $tokens);

        if (isset($res['code']) && $res['code'] === '000') {
            return ['status' => 'SUCCESS', 'message' => 'Paket berhasil dinonaktifkan.'];
        }

        return [
            'status' => 'FAILED',
            'message' => $res['message'] ?? $res['error'] ?? 'Gagal menonaktifkan paket.'
        ];
    }

    /**
     * Get active packages / quotas list.
     */
    public function getActivePackages(array $tokens): array
    {
        if ($this->isSimulationMode()) {
            return [
                [
                    'id' => 'flex_m_quota_1',
                    'name' => 'Xtra Combo Flex M (20GB)',
                    'quota_total' => '20.00 GB',
                    'quota_remaining' => '12.45 GB',
                    'quota_code' => 'flex_m_quota_code',
                    'product_domain' => 'DATA',
                    'product_subscription_type' => 'PREPAID',
                    'expired_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
                ],
                [
                    'id' => 'flex_m_youtube_1',
                    'name' => 'Bonus Youtube 5GB',
                    'quota_total' => '5.00 GB',
                    'quota_remaining' => '0.50 GB',
                    'quota_code' => 'youtube_quota_code',
                    'product_domain' => 'DATA',
                    'product_subscription_type' => 'PREPAID',
                    'expired_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
                ]
            ];
        }

        $res = $this->sendApiRequest('api/v8/packages/active-list', [
            'is_enterprise' => false,
            'lang' => 'en'
        ], $tokens);

        $packages = [];
        if (isset($res['data']['active_list']) && is_array($res['data']['active_list'])) {
            foreach ($res['data']['active_list'] as $pkg) {
                // Parse details
                $packages[] = [
                    'id' => $pkg['product_code'] ?? $pkg['quota_code'] ?? uniqid(),
                    'name' => $pkg['product_name'] ?? 'Paket Internet',
                    'quota_total' => $this->formatHumanSize($pkg['total_volume'] ?? 0),
                    'quota_remaining' => $this->formatHumanSize($pkg['remaining_volume'] ?? 0),
                    'quota_code' => $pkg['quota_code'] ?? '',
                    'product_domain' => $pkg['product_domain'] ?? '',
                    'product_subscription_type' => $pkg['product_subscription_type'] ?? 'PREPAID',
                    'expired_at' => isset($pkg['end_date']) ? date('Y-m-d H:i:s', $pkg['end_date']) : null,
                ];
            }
        }

        return $packages;
    }

    // =========================================================================
    //  Helper Utilities
    // =========================================================================

    /**
     * Convert size in bytes to human-readable size.
     */
    protected function formatHumanSize(float $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Format phone number to international format (628...).
     */
    protected function formatMsisdn(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }
        if (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }
}
