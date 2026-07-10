<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Show the profile edit form.
     */
    public function edit()
    {
        $user = Auth::user();
        return view('profile', [
            'title' => 'Profil Saya',
            'user' => $user,
            'currentBalance' => $user->getBalance(),
        ]);
    }

    /**
     * Update the user profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20',
            'telegram_chat_id' => 'nullable|regex:/^-?[0-9]+$/|max:100',
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Password lama tidak cocok.']);
            }
        }

        DB::transaction(function () use ($user, $request) {
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->telegram_chat_id = $request->telegram_chat_id;
            
            if ($request->filled('new_password')) {
                $user->password = Hash::make($request->new_password);
            }
            
            $user->save();
        });

        return redirect()->route('profile.edit')->with('success', 'Profil Anda berhasil diperbarui.');
    }
}
