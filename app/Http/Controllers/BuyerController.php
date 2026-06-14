<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyerController extends Controller
{
    /**
     * Handle requesting Seller upgrade.
     */
    public function requestUpgradeToSeller()
    {
        $user = Auth::user();

        if ($user->seller_request === 'pending') {
            return back()->with('error', 'Pengajuan upgrade Anda sudah dikirim dan berstatus pending.');
        }

        $user->update([
            'seller_request' => 'pending',
        ]);

        return back()->with('success', 'Permohonan upgrade menjadi Seller berhasil dikirim! Menunggu persetujuan Admin.');
    }
}
