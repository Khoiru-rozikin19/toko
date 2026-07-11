<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Password di-hash
            'role' => 'buyer',
            'is_verified' => false, // Default dinonaktifkan
            'seller_request' => 'none',
        ]);

        // Send Telegram Notification to Admin
        try {
            app(\App\Services\TelegramService::class)->sendUserRegistrationNotification($user);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send Telegram user registration notification: " . $e->getMessage());
        }

        return redirect()->route('login')->with('success', 'Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan verifikasi dari Admin Utama.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
