<?php

use App\Models\User;
use App\Models\XlSession;
use App\Models\Setting;

beforeEach(function () {
    // Enable simulation mode by default for tests
    Setting::set('myxl_simulation_mode', '1');
    Setting::set('myxl_api_base_url', 'https://api.xl.co.id/api/v2');
});

test('guest or non-admin cannot access xl tool index page', function () {
    // Guest
    $response = $this->get('/admin/tools/xl');
    $response->assertRedirect('/login');

    // Buyer
    $buyer = User::factory()->create(['role' => 'buyer', 'is_verified' => true]);
    $response = $this->actingAs($buyer)->get('/admin/tools/xl');
    $response->assertRedirect('/');

    // Seller
    $seller = User::factory()->create(['role' => 'seller', 'is_verified' => true]);
    $response = $this->actingAs($seller)->get('/admin/tools/xl');
    $response->assertRedirect('/');
});

test('admin can access xl tool index page', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);

    $response = $this->actingAs($admin)->get('/admin/tools/xl');

    $response->assertStatus(200);
    $response->assertViewIs('admin.tools.xl');
});

test('admin can request otp in simulation mode', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);

    $response = $this->actingAs($admin)->postJson('/admin/tools/xl/request-otp', [
        'phone' => '087860356425',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '[SIMULASI] OTP berhasil dikirim via SMS ke 6287860356425 (Gunakan kode: 123456)',
        ]);
});

test('admin can verify otp and save session in simulation mode', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);

    $response = $this->actingAs($admin)->postJson('/admin/tools/xl/verify-otp', [
        'phone' => '087860356425',
        'otp' => '123456',
        'label' => 'Test Pelanggan',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Sesi berhasil disimpan untuk Test Pelanggan',
        ]);

    $this->assertDatabaseHas('xl_sessions', [
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
    ]);
});

test('admin can check quota and fetch packages', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'access_token' => 'mock_token',
    ]);

    $response = $this->actingAs($admin)->getJson("/admin/tools/xl/{$session->id}/quota");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'profile' => ['name', 'balance', 'active_until'],
            'packages' => [
                '*' => ['id', 'name', 'quota_total', 'quota_remaining', 'expired_at']
            ]
        ]);

    // Check payload cached in database
    $session->refresh();
    $this->assertNotNull($session->payload);
    $this->assertEquals('Budi Santoso (Simulasi)', $session->payload['profile']['name']);
});

test('admin can unsubscribe a package', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    
    // Create session with pre-filled mock payload packages
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'access_token' => 'mock_token',
        'payload' => [
            'profile' => ['name' => 'Budi Santoso', 'balance' => '50000', 'active_until' => '2026-12-30'],
            'packages' => [
                [
                    'id' => 'pkg_xtra_combo_1',
                    'name' => 'Xtra Combo Flex M (20GB)',
                    'quota_total' => '20.00 GB',
                    'quota_remaining' => '15.42 GB',
                    'expired_at' => '2026-12-30 00:00:00'
                ],
                [
                    'id' => 'pkg_bonus_tiktok',
                    'name' => 'Bonus Kuota TikTok 10GB',
                    'quota_total' => '10.00 GB',
                    'quota_remaining' => '2.15 GB',
                    'expired_at' => '2026-12-30 00:00:00'
                ]
            ]
        ]
    ]);

    // Unsubscribe 'pkg_bonus_tiktok'
    $response = $this->actingAs($admin)->postJson('/admin/tools/xl/unsubscribe', [
        'session_id' => $session->id,
        'package_id' => 'pkg_bonus_tiktok',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Paket berhasil dinonaktifkan.',
        ]);

    $session->refresh();
    
    // Verify package is removed from payload cache in DB
    $this->assertCount(1, $session->payload['packages']);
    $this->assertEquals('pkg_xtra_combo_1', $session->payload['packages'][0]['id']);
});

test('admin can delete session', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'access_token' => 'mock_token',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/tools/xl/{$session->id}");

    $response->assertRedirect('/admin/tools/xl');
    $this->assertDatabaseMissing('xl_sessions', ['id' => $session->id]);
});

test('admin can update MyXL API configuration settings', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/settings', [
        'myxl_api_base_url' => 'https://api.myxl-h2h.com/api/v3',
        'myxl_simulation_mode' => '0',
        'myxl_custom_headers' => '{"X-Channel": "API-TEST"}',
    ]);

    $response->assertRedirect('/admin/tools/xl');

    $this->assertEquals('https://api.myxl-h2h.com/api/v3', Setting::get('myxl_api_base_url'));
    $this->assertEquals('0', Setting::get('myxl_simulation_mode'));
    $this->assertEquals('{"X-Channel": "API-TEST"}', Setting::get('myxl_custom_headers'));
});
