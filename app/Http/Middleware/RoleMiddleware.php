<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = Auth::user();

        // Periksa status verifikasi akun
        if (!$user->is_verified) {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Akun Anda belum disetujui oleh Admin.');
        }

        // Periksa kesesuaian role
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        return redirect()->route('catalog')->with('error', 'Akses ditolak. Anda tidak memiliki izin untuk halaman ini.');
    }
}
