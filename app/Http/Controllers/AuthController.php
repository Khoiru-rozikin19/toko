<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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

        // IP Protection: Batasi maksimal 5 pendaftaran per IP per jam
        $ipKey = 'register-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            $seconds = RateLimiter::availableIn($ipKey);
            return back()->withErrors(['email' => 'Terlalu banyak pendaftaran dari IP ini. Coba lagi dalam ' . ceil($seconds / 60) . ' menit.'])->withInput();
        }

        // WhatsApp OTP Flow
        $whatsappService = app(WhatsappService::class);
        if ($whatsappService->isOtpEnabled()) {
            $phoneKey = 'otp-phone:' . $phone;
            $cooldownKey = 'otp-cooldown:' . $phone;

            if (RateLimiter::tooManyAttempts($phoneKey, 3)) {
                $seconds = RateLimiter::availableIn($phoneKey);
                return back()->withErrors(['phone' => 'Nomor ini telah meminta OTP 3 kali. Silakan coba lagi dalam ' . ceil($seconds / 60) . ' menit.'])->withInput();
            }

            // Catat hit sebelum membuat user (mencegah spam pendaftaran cepat)
            RateLimiter::hit($phoneKey, 3600); // Batas 3 kali dalam 1 jam
            RateLimiter::hit($cooldownKey, 120); // Jeda 2 menit
        }

        RateLimiter::hit($ipKey, 3600); // Catat hit untuk IP

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

        if ($whatsappService->isOtpEnabled()) {
            $otp = (string) rand(100000, 999999);
            $user->update([
                'whatsapp_otp' => $otp,
                'whatsapp_otp_expires_at' => now()->addMinutes(5)
            ]);
            
            try {
                $whatsappService->sendOtp($user->phone, $otp);
            } catch (\Exception $e) {
                // Hapus user yang baru dibuat karena nomor WA tidak valid/aktif
                $user->delete();
                // Reset limiter agar tidak langsung terblokir jika salah input nomor
                if (isset($phoneKey) && isset($cooldownKey)) {
                    RateLimiter::clear($phoneKey);
                    RateLimiter::clear($cooldownKey);
                }
                return back()->withErrors(['phone' => $e->getMessage()])->withInput();
            }
            
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

        $phone = $user->phone;
        $phoneKey = 'otp-phone:' . $phone;
        $cooldownKey = 'otp-cooldown:' . $phone;

        // Cek jika diblokir 1 jam (lebih dari 3 kali meminta)
        if (RateLimiter::tooManyAttempts($phoneKey, 3)) {
            $seconds = RateLimiter::availableIn($phoneKey);
            return back()->withErrors(['otp' => 'Anda telah meminta OTP 3 kali. Silakan coba lagi dalam ' . ceil($seconds / 60) . ' menit.']);
        }

        // Cek jeda kirim ulang (cooldown 2 menit)
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);
            return back()->withErrors(['otp' => 'Tunggu ' . $seconds . ' detik sebelum meminta kode OTP kembali.']);
        }

        // Catat pengiriman OTP baru
        RateLimiter::hit($phoneKey, 3600); // Batas 3 kali dalam 1 jam
        RateLimiter::hit($cooldownKey, 120); // Jeda 2 menit

        $otp = (string) rand(100000, 999999);
        $user->update([
            'whatsapp_otp' => $otp,
            'whatsapp_otp_expires_at' => now()->addMinutes(5)
        ]);

        try {
            $whatsappService = app(WhatsappService::class);
            $whatsappService->sendOtp($user->phone, $otp);
        } catch (\Exception $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

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
