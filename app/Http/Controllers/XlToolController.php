<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\XlSession;
use App\Services\MyXlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XlToolController extends Controller
{
    protected $xlService;

    // Static decoy package configurations from me-cli-sunset
    private $decoys = [
        'default-balance' => [
            'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
            'variant_code' => '719d093f-6f8d-46a4-8390-6a0003a172ea',
            'order' => 1,
            'is_enterprise' => true,
            'migration_type' => 'NONE',
            'price' => 889750,
        ],
        'default-qris' => [
            'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
            'variant_code' => '719d093f-6f8d-46a4-8390-6a0003a172ea',
            'order' => 1,
            'is_enterprise' => true,
            'migration_type' => 'NONE',
            'price' => 889750,
        ],
        'prio-balance' => [
            'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
            'variant_code' => '719d093f-6f8d-46a4-8390-6a0003a172ea',
            'order' => 1,
            'is_enterprise' => true,
            'migration_type' => 'NONE',
            'price' => 889750,
        ],
        'prio-qris' => [
            'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
            'variant_code' => '719d093f-6f8d-46a4-8390-6a0003a172ea',
            'order' => 1,
            'is_enterprise' => true,
            'migration_type' => 'NONE',
            'price' => 889750,
        ]
    ];

    public function __construct(MyXlService $xlService)
    {
        $this->xlService = $xlService;
    }

    /**
     * Dashboard index.
     */
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $sessions = XlSession::orderBy('updated_at', 'desc')->get();
        $activeSession = XlSession::where('is_active', true)->first();

        $profile = null;
        $balance = null;
        $activePackages = [];
        $loyaltyInfo = [];
        $transactions = [];
        $familyData = null;

        // Auto-refresh active token and load fresh data
        if ($activeSession) {
            $tokens = [
                'access_token' => $activeSession->access_token,
                'id_token' => $activeSession->id_token,
                'refresh_token' => $activeSession->refresh_token,
            ];

            // Attempt to fetch profile to verify token validity
            $profile = $this->xlService->getProfile($tokens);
            if (!$profile && $activeSession->refresh_token) {
                // Token likely expired, attempt refresh
                $newTokens = $this->xlService->refreshToken($activeSession->refresh_token);
                if ($newTokens) {
                    $activeSession->update([
                        'access_token' => $newTokens['access_token'],
                        'id_token' => $newTokens['id_token'],
                        'refresh_token' => $newTokens['refresh_token']
                    ]);
                    $tokens = $newTokens;
                    $profile = $this->xlService->getProfile($tokens);
                }
            }

            if ($profile) {
                $balance = $this->xlService->getBalance($tokens);
                $activePackages = $this->xlService->getActivePackages($tokens);
                $loyaltyInfo = $this->xlService->getTieringInfo($tokens);
                $transactions = $this->xlService->getTransactionHistory($tokens);
                
                // If prepaid, load Akrab family organizer details
                if (($profile['profile']['subscription_type'] ?? '') === 'PREPAID') {
                    $familyRes = $this->xlService->getFamilyData($tokens);
                    if (($familyRes['status'] ?? '') === 'SUCCESS') {
                        $familyData = $familyRes['data'] ?? null;
                    }
                }

                // Cache payload to database
                $activeSession->update([
                    'payload' => [
                        'profile' => $profile,
                        'balance' => $balance,
                        'packages' => $activePackages,
                        'loyalty' => $loyaltyInfo,
                        'family' => $familyData,
                        'last_checked' => now()->toIso8601String()
                    ]
                ]);
            } else {
                // If profile loading fails completely, load from cache payload
                $cache = $activeSession->payload ?? [];
                $profile = $cache['profile'] ?? null;
                $balance = $cache['balance'] ?? null;
                $activePackages = $cache['packages'] ?? [];
                $loyaltyInfo = $cache['loyalty'] ?? [];
                $familyData = $cache['family'] ?? null;
            }
        }

        // Settings configuration values for the settings panel form
        $baseUrl = Setting::get('myxl_api_base_url', config('services.myxl.base_api_url'));
        $isSimMode = Setting::get('myxl_simulation_mode', '0') === '1';
        $customHeaders = Setting::get('myxl_custom_headers', '');

        $credentials = [
            'base_ciam_url'       => Setting::get('myxl_base_ciam_url', config('services.myxl.base_ciam_url', '')),
            'basic_auth'          => Setting::get('myxl_basic_auth', config('services.myxl.basic_auth', '')),
            'ax_fp_key'           => Setting::get('myxl_ax_fp_key', config('services.myxl.ax_fp_key', '')),
            'ua'                  => Setting::get('myxl_ua', config('services.myxl.ua', '')),
            'api_key'             => Setting::get('myxl_api_key', config('services.myxl.api_key', '')),
            'encrypted_field_key' => Setting::get('myxl_encrypted_field_key', config('services.myxl.encrypted_field_key', '')),
            'xdata_key'           => Setting::get('myxl_xdata_key', config('services.myxl.xdata_key', '')),
            'ax_api_sig_key'      => Setting::get('myxl_ax_api_sig_key', config('services.myxl.ax_api_sig_key', '')),
            'x_api_base_secret'   => Setting::get('myxl_x_api_base_secret', config('services.myxl.x_api_base_secret', '')),
            'circle_msisdn_key'   => Setting::get('myxl_circle_msisdn_key', config('services.myxl.circle_msisdn_key', '')),
        ];

        // Sample promo options code from me-cli-sunset for UI shopping shortcut
        $hotPromoPackages = [
            [
                'name' => 'Xtra Edukasi 2GB 1 Hari',
                'option_code' => '5110376',
                'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
                'price' => 2000
            ],
            [
                'name' => 'Xtra Conference 15GB 30 Hari',
                'option_code' => '5110461',
                'family_code' => 'b0a20d74-0c54-4e3b-8f3f-01e7482e50bf',
                'price' => 30000
            ]
        ];

        return view('admin.tools.xl', compact(
            'sessions',
            'activeSession',
            'profile',
            'balance',
            'activePackages',
            'loyaltyInfo',
            'transactions',
            'familyData',
            'baseUrl',
            'isSimMode',
            'customHeaders',
            'credentials',
            'hotPromoPackages'
        ));
    }

    /**
     * Switch active session.
     */
    public function changeActiveSession(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate(['session_id' => 'required|exists:xl_sessions,id']);

        XlSession::query()->update(['is_active' => false]);
        XlSession::where('id', $request->session_id)->update(['is_active' => true]);

        return redirect()->route('admin.tools.xl.index')->with('success', 'Akun aktif berhasil diubah.');
    }

    /**
     * Request OTP.
     */
    public function requestOtp(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['phone' => 'required|string|min:9|max:15']);

        $res = $this->xlService->requestOtp($request->phone);
        return response()->json($res);
    }

    /**
     * Verify OTP and register/update session.
     */
    public function verifyOtp(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'phone' => 'required|string|min:9|max:15',
            'otp' => 'required|string|min:4|max:8',
            'label' => 'nullable|string|max:100',
        ]);

        $res = $this->xlService->verifyOtp($request->phone, $request->otp);

        if ($res['success']) {
            $msisdn = preg_replace('/[^0-9]/', '', $request->phone);
            if (str_starts_with($msisdn, '08')) {
                $msisdn = '62' . substr($msisdn, 1);
            }

            // Set all other sessions to inactive
            XlSession::query()->update(['is_active' => false]);

            $session = XlSession::updateOrCreate(
                ['msisdn' => $msisdn],
                [
                    'label' => $request->label ?: 'Nomor XL ' . $msisdn,
                    'subscriber_id' => $res['profile']['subscriber_id'] ?? null,
                    'subscription_type' => $res['profile']['subscription_type'] ?? 'PREPAID',
                    'access_token' => $res['tokens']['access_token'],
                    'id_token' => $res['tokens']['id_token'],
                    'refresh_token' => $res['tokens']['refresh_token'],
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Sesi berhasil disimpan dan diaktifkan untuk ' . $session->label,
            ]);
        }

        return response()->json($res, 400);
    }

    /**
     * Delete session.
     */
    public function deleteSession($id)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $session = XlSession::findOrFail($id);
        $label = $session->label;
        $session->delete();

        // If active session was deleted, fallback to the next available session
        if ($session->is_active) {
            $next = XlSession::first();
            if ($next) {
                $next->update(['is_active' => true]);
            }
        }

        return redirect()->route('admin.tools.xl.index')->with('success', 'Sesi untuk ' . $label . ' berhasil dihapus.');
    }

    /**
     * Purchase package using Balance, QRIS, and/or Decoy.
     */
    public function purchasePackage(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'purchase_type' => 'required|in:option_code,family_code',
            'option_code' => 'required_if:purchase_type,option_code|nullable|string',
            'family_code' => 'required_if:purchase_type,family_code|nullable|string',
            'payment_method' => 'required|in:balance,qris',
            'use_decoy' => 'required|in:0,1',
        ]);

        $activeSession = XlSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->withErrors(['error' => 'Tidak ada akun aktif yang dipilih. Silakan login atau pilih akun terlebih dahulu.']);
        }

        $tokens = [
            'access_token' => $activeSession->access_token,
            'id_token' => $activeSession->id_token,
            'refresh_token' => $activeSession->refresh_token,
        ];
        $apiKey = Setting::get('myxl_api_key', config('services.myxl.api_key', ''));

        // Load Decoy details if requested
        $decoyPkg = null;
        if ($request->use_decoy === '1') {
            $prefix = ($activeSession->subscription_type === 'PREPAID') ? 'default-' : 'prio-';
            $decoyKey = $prefix . ($request->payment_method === 'qris' ? 'qris' : 'balance');
            $decoyConfig = $this->decoys[$decoyKey] ?? null;

            if ($decoyConfig) {
                $decoyPkg = $this->xlService->getPackage($tokens, $decoyConfig['family_code']); // fallback
                // If family code detail fails, use static placeholder fields
                if (!$decoyPkg) {
                    $decoyPkg = [
                        'package_option' => [
                            'package_option_code' => 'option_xl_pass_decoy',
                            'price' => $decoyConfig['price'],
                            'name' => 'XL PASS Decoy Package'
                        ],
                        'token_confirmation' => 'decoy_confirmation_token_simulated'
                    ];
                }
            }
        }

        // Fetch target package details
        $targetPkg = null;
        if ($request->purchase_type === 'option_code') {
            $targetPkg = $this->xlService->getPackage($tokens, $request->option_code);
        } else {
            // Load by family details (takes first package option from family listing)
            $familyRes = $this->xlService->getFamily($tokens, $request->family_code);
            if ($familyRes && isset($familyRes['package_variants'][0]['package_options'][0]['package_option_code'])) {
                $optCode = $familyRes['package_variants'][0]['package_options'][0]['package_option_code'];
                $targetPkg = $this->xlService->getPackage($tokens, $optCode, $request->family_code);
            }
        }

        if (!$targetPkg) {
            return back()->withErrors(['error' => 'Gagal memuat informasi detail paket target. Periksa kembali kode paket/family.']);
        }

        // Build payment items array
        $paymentItems = [];
        $paymentItems[] = [
            'item_code' => $targetPkg['package_option']['package_option_code'],
            'product_type' => '',
            'item_price' => $targetPkg['package_option']['price'],
            'item_name' => rand(1000, 9999) . ' ' . $targetPkg['package_option']['name'],
            'tax' => 0,
            'token_confirmation' => $targetPkg['token_confirmation'] ?? '',
        ];

        if ($decoyPkg) {
            $paymentItems[] = [
                'item_code' => $decoyPkg['package_option']['package_option_code'],
                'product_type' => '',
                'item_price' => $decoyPkg['package_option']['price'],
                'item_name' => rand(1000, 9999) . ' ' . $decoyPkg['package_option']['name'],
                'tax' => 0,
                'token_confirmation' => $decoyPkg['token_confirmation'] ?? '',
            ];
        }

        // Calculate amount to spend
        $amount = $targetPkg['package_option']['price'];
        if ($decoyPkg) {
            $amount += $decoyPkg['package_option']['price'];
        }

        if ($request->payment_method === 'balance') {
            $res = $this->xlService->settlementBalance($tokens, $paymentItems, $amount, 'BUY_PACKAGE');
            if (isset($res['status']) && $res['status'] === 'SUCCESS') {
                return redirect()->route('admin.tools.xl.index')->with('success', 'Pembelian paket menggunakan pulsa berhasil diinisiasi!');
            }
            return back()->withErrors(['error' => $res['message'] ?? 'Gagal membeli paket menggunakan pulsa.']);
        } else {
            // QRIS flow
            $trxCode = $this->xlService->settlementQris($tokens, $paymentItems, $amount, 'BUY_PACKAGE');
            if ($trxCode) {
                $qrPayload = $this->xlService->getQrisCode($tokens, $trxCode);
                if ($qrPayload) {
                    return redirect()->route('admin.tools.xl.index')
                        ->with('success', 'Kode pembayaran QRIS berhasil dibuat!')
                        ->with('qris_code', $qrPayload)
                        ->with('qris_amount', $amount)
                        ->with('qris_trx', $trxCode);
                }
            }
            return back()->withErrors(['error' => 'Gagal membuat invoice QRIS. Silakan hubungi developer atau coba lagi.']);
        }
    }

    /**
     * Stop package subscription.
     */
    public function unsubscribePackage(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'quota_code' => 'required|string',
            'product_domain' => 'required|string',
            'product_subscription_type' => 'required|string',
        ]);

        $activeSession = XlSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->withErrors(['error' => 'Tidak ada akun aktif yang dipilih.']);
        }

        $tokens = [
            'access_token' => $activeSession->access_token,
            'id_token' => $activeSession->id_token,
            'refresh_token' => $activeSession->refresh_token,
        ];

        $res = $this->xlService->unsubscribe($tokens, $request->quota_code, $request->product_domain, $request->product_subscription_type);
        if ($res['status'] === 'SUCCESS') {
            return redirect()->route('admin.tools.xl.index')->with('success', 'Paket berhasil dinonaktifkan.');
        }

        return back()->withErrors(['error' => $res['message']]);
    }

    /**
     * Family Akrab: Change member.
     */
    public function changeFamilyMember(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'parent_alias' => 'required|string|max:50',
            'child_alias' => 'required|string|max:50',
            'slot_id' => 'required|string',
            'family_member_id' => 'required|string',
            'target_msisdn' => 'required|string|min:9|max:15'
        ]);

        $activeSession = XlSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->withErrors(['error' => 'Tidak ada akun aktif yang dipilih.']);
        }

        $tokens = [
            'access_token' => $activeSession->access_token,
            'id_token' => $activeSession->id_token,
            'refresh_token' => $activeSession->refresh_token,
        ];

        // Format phone to international
        $msisdn = preg_replace('/[^0-9]/', '', $request->target_msisdn);
        if (str_starts_with($msisdn, '08')) {
            $msisdn = '62' . substr($msisdn, 1);
        }

        // Validate MSISDN role state first
        $val = $this->xlService->validateMsisdn($tokens, $msisdn);
        if (($val['status'] ?? '') !== 'SUCCESS' || ($val['data']['family_plan_role'] ?? 'NO_ROLE') !== 'NO_ROLE') {
            return back()->withErrors(['error' => 'Nomor HP tidak valid atau sudah bergabung di grup Akrab/Family lain.']);
        }

        $res = $this->xlService->changeMember($tokens, $request->parent_alias, $request->child_alias, $request->slot_id, $request->family_member_id, $msisdn);
        if (($res['status'] ?? '') === 'SUCCESS') {
            return redirect()->route('admin.tools.xl.index')->with('success', 'Anggota grup Akrab berhasil ditambahkan!');
        }

        return back()->withErrors(['error' => $res['message'] ?? 'Gagal menambahkan anggota.']);
    }

    /**
     * Family Akrab: Remove member.
     */
    public function removeFamilyMember(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate(['family_member_id' => 'required|string']);

        $activeSession = XlSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->withErrors(['error' => 'Tidak ada akun aktif yang dipilih.']);
        }

        $tokens = [
            'access_token' => $activeSession->access_token,
            'id_token' => $activeSession->id_token,
            'refresh_token' => $activeSession->refresh_token,
        ];

        $res = $this->xlService->removeMember($tokens, $request->family_member_id);
        if (($res['status'] ?? '') === 'SUCCESS') {
            return redirect()->route('admin.tools.xl.index')->with('success', 'Anggota berhasil dikeluarkan dari grup Akrab.');
        }

        return back()->withErrors(['error' => $res['message'] ?? 'Gagal mengeluarkan anggota.']);
    }

    /**
     * Family Akrab: Set quota limit.
     */
    public function setFamilyQuotaLimit(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'family_member_id' => 'required|string',
            'original_allocation' => 'required|numeric',
            'quota_limit_mb' => 'required|numeric|min:0'
        ]);

        $activeSession = XlSession::where('is_active', true)->first();
        if (!$activeSession) {
            return back()->withErrors(['error' => 'Tidak ada akun aktif yang dipilih.']);
        }

        $tokens = [
            'access_token' => $activeSession->access_token,
            'id_token' => $activeSession->id_token,
            'refresh_token' => $activeSession->refresh_token,
        ];

        $newAllocation = (int) $request->quota_limit_mb * 1024 * 1024; // convert MB to bytes

        $res = $this->xlService->setQuotaLimit($tokens, (int) $request->original_allocation, $newAllocation, $request->family_member_id);
        if (($res['status'] ?? '') === 'SUCCESS') {
            return redirect()->route('admin.tools.xl.index')->with('success', 'Limit kuota anggota berhasil diperbarui!');
        }

        return back()->withErrors(['error' => $res['message'] ?? 'Gagal memperbarui limit kuota anggota.']);
    }

    /**
     * Update settings.
     */
    public function updateSettings(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'myxl_api_base_url' => 'nullable|url',
            'myxl_simulation_mode' => 'required|in:0,1',
            'myxl_custom_headers' => 'nullable|string',
        ]);

        Setting::set('myxl_api_base_url', $request->myxl_api_base_url);
        Setting::set('myxl_simulation_mode', $request->myxl_simulation_mode);
        Setting::set('myxl_custom_headers', $request->myxl_custom_headers);

        $credentialKeys = [
            'base_ciam_url', 'basic_auth', 'ax_fp_key', 'ua', 'api_key',
            'encrypted_field_key', 'xdata_key', 'ax_api_sig_key',
            'x_api_base_secret', 'circle_msisdn_key',
        ];
        foreach ($credentialKeys as $key) {
            $fieldName = 'myxl_' . $key;
            if ($request->has($fieldName)) {
                Setting::set($fieldName, $request->input($fieldName));
            }
        }

        return redirect()->route('admin.tools.xl.index')->with('success', 'Konfigurasi API MyXL berhasil diperbarui.');
    }
}
