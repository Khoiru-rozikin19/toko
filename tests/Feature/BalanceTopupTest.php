<?php

use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Seed QRIS Static setting
    Setting::set(
        'qris_static_string', 
        '00020101021126610014COM.GO-JEK.WWW01189360091430224274230210G0224274230303UMI51440014ID.CO.QRIS.WWW0215ID10243581829610303UMI5204573253033605802ID5919Rzk store, SNR PNNJ6003OKU61053215962070703A016304881F'
    );
});

test('authenticated user can request topup', function () {
    $user = User::factory()->create([
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/balance/topup', [
            'amount' => 50000,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'order' => [
                'id',
                'topup_amount',
                'total_amount',
                'qris_payload',
                'expired_at',
                'server_time',
            ]
        ]);
});

test('topup validation fails for low amount', function () {
    $user = User::factory()->create([
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/balance/topup', [
            'amount' => 5000, // less than 10000
        ]);

    $response->assertStatus(422);
});
