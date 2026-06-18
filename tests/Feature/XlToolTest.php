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
            'message' => 'Sesi berhasil disimpan dan diaktifkan untuk Test Pelanggan',
        ]);

    $this->assertDatabaseHas('xl_sessions', [
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);
});

test('admin can change active session', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    
    $session1 = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Sesi 1',
        'is_active' => true,
    ]);
    
    $session2 = XlSession::create([
        'msisdn' => '6287860356426',
        'label' => 'Sesi 2',
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/active', [
        'session_id' => $session2->id,
    ]);

    $response->assertRedirect('/admin/tools/xl');
    
    $session1->refresh();
    $session2->refresh();
    
    expect($session1->is_active)->toBeFalse();
    expect($session2->is_active)->toBeTrue();
});

test('admin can purchase package in simulation mode using balance', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/purchase', [
        'purchase_type' => 'option_code',
        'option_code' => '5110376',
        'payment_method' => 'balance',
        'use_decoy' => '1',
    ]);

    $response->assertRedirect('/admin/tools/xl');
    $response->assertSessionHas('success', 'Pembelian paket menggunakan pulsa berhasil diinisiasi!');
});

test('admin can purchase package in simulation mode using qris', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/purchase', [
        'purchase_type' => 'option_code',
        'option_code' => '5110376',
        'payment_method' => 'qris',
        'use_decoy' => '0',
    ]);

    $response->assertRedirect('/admin/tools/xl');
    $response->assertSessionHas('success', 'Kode pembayaran QRIS berhasil dibuat!');
    $response->assertSessionHas('qris_code');
});

test('admin can unsubscribe a package in simulation mode', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/unsubscribe', [
        'quota_code' => 'flex_m_quota_code',
        'product_domain' => 'DATA',
        'product_subscription_type' => 'PREPAID',
    ]);

    $response->assertRedirect('/admin/tools/xl');
    $response->assertSessionHas('success', 'Paket berhasil dinonaktifkan.');
});

test('admin can manage family akrab members in simulation mode', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);

    // Change/Add Member
    $response1 = $this->actingAs($admin)->post('/admin/tools/xl/family/member', [
        'parent_alias' => 'Organizer',
        'child_alias' => 'Anak',
        'slot_id' => 'slot_1',
        'family_member_id' => 'member_id_1',
        'target_msisdn' => '087860356427',
    ]);
    $response1->assertRedirect('/admin/tools/xl');
    $response1->assertSessionHas('success', 'Anggota grup Akrab berhasil ditambahkan!');

    // Set Quota Limit
    $response2 = $this->actingAs($admin)->post('/admin/tools/xl/family/quota', [
        'family_member_id' => 'member_id_1',
        'original_allocation' => '5368709120',
        'quota_limit_mb' => '10240',
    ]);
    $response2->assertRedirect('/admin/tools/xl');
    $response2->assertSessionHas('success', 'Limit kuota anggota berhasil diperbarui!');

    // Remove Member
    $response3 = $this->actingAs($admin)->post('/admin/tools/xl/family/member/remove', [
        'family_member_id' => 'member_id_1',
    ]);
    $response3->assertRedirect('/admin/tools/xl');
    $response3->assertSessionHas('success', 'Anggota berhasil dikeluarkan dari grup Akrab.');
});

test('admin can delete session', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);
    $session = XlSession::create([
        'msisdn' => '6287860356425',
        'label' => 'Test Pelanggan',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/tools/xl/{$session->id}");

    $response->assertRedirect('/admin/tools/xl');
    $this->assertDatabaseMissing('xl_sessions', ['id' => $session->id]);
});

test('admin can update MyXL API configuration settings', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_verified' => true]);

    $response = $this->actingAs($admin)->post('/admin/tools/xl/settings', [
        'myxl_api_base_url' => 'https://api.myxl-h2h.com/api/v3',
        'myxl_simulation_mode' => '1',
        'myxl_custom_headers' => '{"X-Channel": "API-TEST"}',
    ]);

    $response->assertRedirect('/admin/tools/xl');

    $this->assertEquals('https://api.myxl-h2h.com/api/v3', Setting::get('myxl_api_base_url'));
    $this->assertEquals('1', Setting::get('myxl_simulation_mode'));
    $this->assertEquals('{"X-Channel": "API-TEST"}', Setting::get('myxl_custom_headers'));
});
