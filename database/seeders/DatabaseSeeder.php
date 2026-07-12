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
            '00020101021126570011ID.DANA.WWW011893600915302634402802090263440280303UMI51440014ID.CO.QRIS.WWW0215ID10265391682640303UMI5204481453033605802ID5908rz store6015Kab. Ogan Komer610532159630485BE'
        );
        Setting::set('api_secret_key', 'rahasiahappy123');

        // 3. Create Categories
        $kuotaXl = \App\Models\Category::updateOrCreate(['name' => 'Kuota XL']);
        $digital = \App\Models\Category::updateOrCreate(['name' => 'Digital']);

    }
}
