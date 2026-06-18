<?php

namespace App\Http\Controllers;

use App\Models\XlSession;
use App\Services\MyXlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XlToolController extends Controller
{
    protected $xlService;

    public function __construct(MyXlService $xlService)
    {
        $this->xlService = $xlService;
    }

    /**
     * Display listing of sessions and tools dashboard.
     */
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $sessions = XlSession::orderBy('updated_at', 'desc')->get();
        
        // Settings configuration values
        $baseUrl = \App\Models\Setting::get('myxl_api_base_url', 'https://api.xl.co.id/api/v2');
        $isSimMode = \App\Models\Setting::get('myxl_simulation_mode', '1') === '1';
        $customHeaders = \App\Models\Setting::get('myxl_custom_headers', '');

        return view('admin.tools.xl', compact('sessions', 'baseUrl', 'isSimMode', 'customHeaders'));
    }

    /**
     * Request OTP.
     */
    public function requestOtp(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'phone' => 'required|string|min:9|max:15',
        ]);

        $res = $this->xlService->requestOtp($request->phone);

        return response()->json($res);
    }

    /**
     * Verify OTP and save/update session.
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
            // Format phone to international
            $msisdn = preg_replace('/[^0-9]/', '', $request->phone);
            if (str_starts_with($msisdn, '08')) {
                $msisdn = '62' . substr($msisdn, 1);
            }

            // Save/update session in DB
            $session = XlSession::updateOrCreate(
                ['msisdn' => $msisdn],
                [
                    'label' => $request->label ?: 'Nomor XL ' . $msisdn,
                    'access_token' => $res['token'],
                    'payload' => $res['profile'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Sesi berhasil disimpan untuk ' . $session->label,
            ]);
        }

        return response()->json($res, 400);
    }

    /**
     * Get active packages / quota.
     */
    public function checkQuota($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = XlSession::findOrFail($id);

        $res = $this->xlService->fetchDashboard($session->access_token);

        if ($res['success']) {
            // Update payload cache in DB
            $session->update([
                'payload' => [
                    'profile' => $res['profile'] ?? null,
                    'packages' => $res['packages'] ?? [],
                    'last_checked' => now()->toIso8601String(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'profile' => $res['profile'] ?? null,
                'packages' => $res['packages'] ?? [],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $res['message'] ?? 'Gagal mengambil data kuota dari server XL. Token sesi Anda mungkin telah berakhir.',
        ], 400);
    }

    /**
     * Stop/unsubscribe package.
     */
    public function unsubscribePackage(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'session_id' => 'required|exists:xl_sessions,id',
            'package_id' => 'required|string',
        ]);

        $session = XlSession::findOrFail($request->session_id);

        $res = $this->xlService->unsubscribePackage($session->access_token, $request->package_id);

        if ($res['success']) {
            // Clear or update cached payload since a package was deleted
            $payload = $session->payload;
            if (isset($payload['packages']) && is_array($payload['packages'])) {
                $payload['packages'] = array_values(array_filter($payload['packages'], function ($pkg) use ($request) {
                    return $pkg['id'] !== $request->package_id;
                }));
                $session->update(['payload' => $payload]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paket berhasil dinonaktifkan.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $res['message'] ?? 'Gagal menonaktifkan paket.',
        ], 400);
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

        return redirect()->route('admin.tools.xl.index')->with('success', 'Sesi untuk ' . $label . ' berhasil dihapus.');
    }

    /**
     * Update settings (Base URL, Simulation Mode, Custom Headers).
     */
    public function updateSettings(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403);
        }

        $request->validate([
            'myxl_api_base_url' => 'required|url',
            'myxl_simulation_mode' => 'required|in:0,1',
            'myxl_custom_headers' => 'nullable|string',
        ]);

        \App\Models\Setting::set('myxl_api_base_url', $request->myxl_api_base_url);
        \App\Models\Setting::set('myxl_simulation_mode', $request->myxl_simulation_mode);
        \App\Models\Setting::set('myxl_custom_headers', $request->myxl_custom_headers);

        return redirect()->route('admin.tools.xl.index')->with('success', 'Konfigurasi API MyXL berhasil diperbarui.');
    }
}
