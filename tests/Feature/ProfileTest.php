<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guest is redirected to login when accessing profile page', function () {
    $response = $this->get(route('profile.edit'));
    $response->assertRedirect('/login');
});

test('authenticated user can view profile page', function () {
    $user = User::create([
        'name' => 'Profile User',
        'email' => 'profile@example.com',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)->get(route('profile.edit'));
    $response->assertStatus(200);
    $response->assertSee('profile@example.com');
    $response->assertSee('Profile User');
});

test('user can update profile details without password change', function () {
    $user = User::create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'password' => Hash::make('password123'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '08987654321',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('success', 'Profil Anda berhasil diperbarui.');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
    expect($user->phone)->toBe('08987654321');
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('user can update password with correct current password', function () {
    $user = User::create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'password' => Hash::make('password123'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'current_password' => 'password123',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('success', 'Profil Anda berhasil diperbarui.');

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('user cannot update password with incorrect current password', function () {
    $user = User::create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'password' => Hash::make('password123'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'current_password' => 'wrongpassword',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors(['current_password' => 'Password lama tidak cocok.']);

    $user->refresh();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('user cannot change email to already taken email', function () {
    User::create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'phone' => '0811111111',
        'password' => Hash::make('password'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $user = User::create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'phone' => '08123456789',
        'password' => Hash::make('password123'),
        'role' => 'buyer',
        'is_verified' => true,
    ]);

    $response = $this->actingAs($user)->post(route('profile.update'), [
        'name' => 'Original Name',
        'email' => 'other@example.com',
        'phone' => '08123456789',
    ]);

    $response->assertSessionHasErrors(['email']);
    $user->refresh();
    expect($user->email)->toBe('original@example.com');
});
