<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin' || $user->role === 'seller') {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->route('catalog');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();

            // Pengecekan status verifikasi akun
            if (!$user->is_verified) {
                $whatsappService = app(WhatsappService::class);
                if ($whatsappService->isOtpEnabled()) {
                    // Kirim OTP Baru
                    $otp = (string) rand(100000, 999999);
                    $user->update([
                        'whatsapp_otp' => $otp,
                        'whatsapp_otp_expires_at' => now()->addMinutes(5)
                    ]);
                    
                    $whatsappService->sendOtp($user->phone, $otp);
                    
                    // Logout kembali demi keamanan session
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    session(['verify_user_id' => $user->id]);
                    return redirect()->route('verify_otp')->with('success', 'Akun Anda belum aktif. Kode OTP baru telah dikirimkan ke WhatsApp Anda.');
                }

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'Akun Anda belum disetujui oleh Admin Utama.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            
            if ($user->role === 'admin' || $user->role === 'seller') {
                return redirect()->intended(route('admin.dashboard'));
            }
            return redirect()->intended(route('catalog'));
        }

        return back()->withErrors([
            'email' => 'Kredensial yang diberikan tidak cocok dengan catatan kami.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('catalog');
        }
        return view('admin.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Bersihkan nomor telepon: hilangkan karakter non-angka dan ubah 08 menjadi 628
        $phone = trim($request->phone);
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Password di-hash
            'role' => 'buyer',
            'is_verified' => false, // Default dinonaktifkan
            'seller_request' => 'none',
        ]);

        // Kirim Notifikasi Telegram ke Admin
        try {
            app(\App\Services\TelegramService::class)->sendUserRegistrationNotification($user);
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram user registration notification: " . $e->getMessage());
        }

        // WhatsApp OTP Flow
        $whatsappService = app(WhatsappService::class);
        if ($whatsappService->isOtpEnabled()) {
            $otp = (string) rand(100000, 999999);
            $user->update([
                'whatsapp_otp' => $otp,
                'whatsapp_otp_expires_at' => now()->addMinutes(5)
            ]);
            
            $whatsappService->sendOtp($user->phone, $otp);
            
            session(['verify_user_id' => $user->id]);
            return redirect()->route('verify_otp')->with('success', 'Pendaftaran berhasil! Kode OTP verifikasi telah dikirimkan ke nomor WhatsApp Anda.');
        }

        return redirect()->route('login')->with('success', 'Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan verifikasi dari Admin Utama.');
    }

    public function showVerifyOtp()
    {
        if (!session()->has('verify_user_id')) {
            return redirect()->route('login');
        }
        $user = User::find(session('verify_user_id'));
        if (!$user) {
            session()->forget('verify_user_id');
            return redirect()->route('login');
        }
        return view('auth.verify-otp', compact('user'));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        if (!session()->has('verify_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('verify_user_id'));
        if (!$user) {
            session()->forget('verify_user_id');
            return redirect()->route('login');
        }

        if ($user->whatsapp_otp !== $request->otp || now()->greaterThan($user->whatsapp_otp_expires_at)) {
            return back()->withErrors(['otp' => 'Kode OTP tidak valid atau telah kedaluwarsa.']);
        }

        // Verifikasi dan aktifkan user
        $user->update([
            'is_verified' => true,
            'whatsapp_otp' => null,
            'whatsapp_otp_expires_at' => null,
        ]);

        session()->forget('verify_user_id');

        // Loginkan pengguna secara otomatis
        Auth::login($user, true);

        if ($user->role === 'admin' || $user->role === 'seller') {
            return redirect()->route('admin.dashboard')->with('success', 'Akun berhasil diverifikasi dan diaktifkan!');
        }
        return redirect()->route('catalog')->with('success', 'Akun berhasil diverifikasi dan diaktifkan!');
    }

    public function resendOtp()
    {
        if (!session()->has('verify_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('verify_user_id'));
        if (!$user) {
            session()->forget('verify_user_id');
            return redirect()->route('login');
        }

        $otp = (string) rand(100000, 999999);
        $user->update([
            'whatsapp_otp' => $otp,
            'whatsapp_otp_expires_at' => now()->addMinutes(5)
        ]);

        $whatsappService = app(WhatsappService::class);
        $whatsappService->sendOtp($user->phone, $otp);

        return back()->with('success', 'Kode OTP baru telah dikirimkan ke WhatsApp Anda.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
