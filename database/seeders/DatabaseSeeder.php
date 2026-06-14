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
        User::updateOrCreate(
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
            '00020101021126570011ID.CO.GOPAY.WWW011893600912345678901202151234567890123450303UME51440014ID.CO.QRIS.WWW02151234567890123450303UME5204599953033605802ID5915GoPay Merchant6009TANGERANG61051511162070703A016304'
        );
        Setting::set('api_secret_key', 'rahasiahappy123');

        // 3. Create Sample Products
        Product::updateOrCreate(
            ['name' => 'Gmail Fresh Worker 1'],
            [
                'price' => 5000,
                'duration_days' => 30,
                'config_template' => "client\ndev tun\nproto udp\nremote sg.vpn.example.com 1194\nresolv-retry infinite\nnobind\npersist-key\npersist-tun\nca [inline]\ncert [inline]\nkey [inline]\nverb 3\n<ca>\n-----BEGIN CERTIFICATE-----\nMOCK_CA_CERT_DATA\n-----END CERTIFICATE-----\n</ca>",
                'stock' => 21,
            ]
        );

        Product::updateOrCreate(
            ['name' => 'Gmail Fresh Worker 3'],
            [
                'price' => 5000,
                'duration_days' => 30,
                'config_template' => "client\ndev tun\nremote sg3.vpn.example.com 1194\nca [inline]\n<ca>\n-----BEGIN CERTIFICATE-----\nMOCK_CA_CERT_DATA\n-----END CERTIFICATE-----\n</ca>",
                'stock' => 30,
            ]
        );

        Product::updateOrCreate(
            ['name' => 'Gmail Fresh Worker 2'],
            [
                'price' => 5000,
                'duration_days' => 30,
                'config_template' => "client\ndev tun\nremote sg2.vpn.example.com 1194\nca [inline]\n<ca>\n-----BEGIN CERTIFICATE-----\nMOCK_CA_CERT_DATA\n-----END CERTIFICATE-----\n</ca>",
                'stock' => 10,
            ]
        );

        Product::updateOrCreate(
            ['name' => 'GHS Bekas'],
            [
                'price' => 10000,
                'duration_days' => 15,
                'config_template' => 'Mock configuration text for GHS Bekas.',
                'stock' => 0,
            ]
        );

        Product::updateOrCreate(
            ['name' => 'GitHub Student Developer Pack'],
            [
                'price' => 10000,
                'duration_days' => 30,
                'config_template' => 'Mock configuration text for GitHub Student Developer Pack.',
                'stock' => 0,
            ]
        );
    }
}
