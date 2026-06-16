<?php

use App\Models\User;
use App\Models\VpsServer;
use App\Models\Product;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

uses(RefreshDatabase::class);

test('admin can manage VPS servers but seller and guest cannot', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@vps.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);

    $seller = User::create([
        'name' => 'Seller User',
        'email' => 'seller@vps.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);

    // 1. Guest access denied
    $this->get(route('admin.vpn_panel'))->assertRedirect(route('login'));
    $this->post(route('admin.vpn_panel.store'), ['name' => 'Server 1'])->assertRedirect(route('login'));

    // 2. Seller access denied
    $this->actingAs($seller)->get(route('admin.vpn_panel'))->assertRedirect(route('catalog'));
    $this->actingAs($seller)->post(route('admin.vpn_panel.store'), ['name' => 'Server 1'])->assertRedirect(route('catalog'));

    // 3. Admin access allowed (store, update, delete)
    $this->actingAs($admin)->get(route('admin.vpn_panel'))->assertStatus(200);

    $storeResponse = $this->actingAs($admin)->post(route('admin.vpn_panel.store'), [
        'name' => 'SG VPS-1',
        'ip_address' => '128.199.40.50',
        'ssh_port' => 22,
        'ssh_username' => 'root',
        'ssh_password' => 'secretpwd',
    ]);
    $storeResponse->assertRedirect(route('admin.vpn_panel'));

    $server = VpsServer::where('name', 'SG VPS-1')->first();
    expect($server)->not->toBeNull();
    expect($server->ip_address)->toBe('128.199.40.50');

    // Update
    $updateResponse = $this->actingAs($admin)->post(route('admin.vpn_panel.update', $server->id), [
        'name' => 'SG VPS-1 Updated',
        'ip_address' => '128.199.40.55',
        'ssh_port' => 2222,
        'ssh_username' => 'admin',
        'ssh_password' => 'newpwd',
    ]);
    $updateResponse->assertRedirect(route('admin.vpn_panel'));
    
    $server->refresh();
    expect($server->name)->toBe('SG VPS-1 Updated');
    expect($server->ssh_port)->toBe(2222);

    // Delete
    $deleteResponse = $this->actingAs($admin)->post(route('admin.vpn_panel.delete', $server->id));
    $deleteResponse->assertRedirect(route('admin.vpn_panel'));
    expect(VpsServer::find($server->id))->toBeNull();
});

test('successful payment callback triggers VPS account creator and sets vpn_config', function () {
    Setting::set('api_secret_key', 'mysecret');

    $server = VpsServer::create([
        'name' => 'Test VPS',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_username' => 'root',
        'ssh_password' => 'password',
    ]);

    $product = Product::create([
        'name' => 'Premium SSH VPS',
        'price' => 10000,
        'duration_days' => 30,
        'stock' => 10,
        'vps_server_id' => $server->id,
        'vps_command_template' => 'create-user {username} {duration}',
    ]);

    $order = Order::create([
        'id' => 'ORD-VPSTEST',
        'product_id' => $product->id,
        'email_or_whatsapp' => 'client@vps.com',
        'base_amount' => 10000,
        'unique_code' => 5,
        'total_amount' => 10005,
        'status' => 'pending',
        'expired_at' => Carbon::now()->addMinutes(15),
    ]);

    // Mock VpsSshService to simulate remote command execution output
    $sshServiceMock = Mockery::mock(\App\Services\VpsSshService::class);
    $sshServiceMock->shouldReceive('createVpnAccount')
                   ->once()
                   ->with(Mockery::on(function ($argOrder) use ($order) {
                       return $argOrder->id === $order->id;
                   }))
                   ->andReturnUsing(function ($argOrder) {
                       $argOrder->vpn_config = "host: 127.0.0.1\nuser: clientvpstest\npass: pass123";
                   });
    app()->instance(\App\Services\VpsSshService::class, $sshServiceMock);

    // Send payment callback
    $response = $this->postJson(route('api.payment.callback'), [
        'raw_text' => 'GOPAY RECEIVED Rp 10.005',
        'amount' => 10005,
        'secret_key' => 'mysecret',
    ]);
    
    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $order->refresh();
    expect($order->status)->toBe('success');
    expect($order->vpn_config)->toContain('host: 127.0.0.1');
    expect($order->vpn_config)->toContain('user: clientvpstest');
});
