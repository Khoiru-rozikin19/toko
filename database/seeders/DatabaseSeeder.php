<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@vpn.com'],
            [
                'name' => 'Admin Utama',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_verified' => true,
                'phone' => '081234567890',
            ]
        );

        // 2. Create Initial Settings (Real-format static QRIS mockup and API Key)
        Setting::set(
            'qris_static_string', 
            '00020101021126610014COM.GO-JEK.WWW01189360091430224274230210G0224274230303UMI51440014ID.CO.QRIS.WWW0215ID10243581829610303UMI5204573253033605802ID5919Rzk store, SNR PNNJ6003OKU61053215962070703A016304881F'
        );
        Setting::set('api_secret_key', 'rahasiahappy123');

        // 3. Create Categories
        $kuotaXl = \App\Models\Category::updateOrCreate(['name' => 'Kuota XL']);
        $digital = \App\Models\Category::updateOrCreate(['name' => 'Digital']);
        $topUpGame = \App\Models\Category::updateOrCreate(['name' => 'Top up gaame']);

    }
}
