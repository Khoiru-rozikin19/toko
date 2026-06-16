<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guests can view homepage catalog and filter by category but get redirected to login for checkout, history and profile', function () {
    // Create categories
    $cat1 = Category::create(['name' => 'Category A']);
    $cat2 = Category::create(['name' => 'Category B']);

    // Create a product under Category A
    $product = Product::create([
        'name' => 'Product X',
        'category_id' => $cat1->id,
        'price' => 5000,
        'duration_days' => 30,
        'stock' => 10,
    ]);

    // Guest views catalog
    $response = $this->get('/');
    $response->assertStatus(200)
             ->assertSee('Product X')
             ->assertSee('Category A')
             ->assertSee('Category B');

    // Guest filters by Category A
    $responseCat1 = $this->get('/?category_id=' . $cat1->id);
    $responseCat1->assertStatus(200)
                 ->assertSee('Product X');

    // Guest filters by Category B (empty)
    $responseCat2 = $this->get('/?category_id=' . $cat2->id);
    $responseCat2->assertStatus(200)
                 ->assertDontSee('Product X');

    // Guest tries to access profile, history, or check status
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->get(route('orders.history'))->assertRedirect(route('login'));
});

test('admin can manage categories via ajax but seller/guest cannot', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_verified' => true,
    ]);

    $seller = User::create([
        'name' => 'Seller User',
        'email' => 'seller@test.com',
        'password' => Hash::make('password'),
        'role' => 'seller',
        'is_verified' => true,
    ]);

    // Guest cannot create category
    auth()->logout();
    $this->post(route('admin.categories.store'), ['name' => 'New Cat'])
         ->assertRedirect(route('login'));

    // Seller cannot create category
    $this->actingAs($seller)
         ->post(route('admin.categories.store'), ['name' => 'New Cat'])
         ->assertRedirect(route('catalog'));

    // Admin can create category
    $response = $this->actingAs($admin)
                     ->postJson(route('admin.categories.store'), ['name' => 'New Cat']);
    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $category = Category::where('name', 'New Cat')->first();
    expect($category)->not->toBeNull();

    // Guest cannot delete category
    auth()->logout();
    $this->delete(route('admin.categories.delete', $category->id))
         ->assertRedirect(route('login'));

    // Seller cannot delete category
    $this->actingAs($seller)
         ->delete(route('admin.categories.delete', $category->id))
         ->assertRedirect(route('catalog'));

    // Admin can delete category
    auth()->logout();
    $responseDel = $this->actingAs($admin)
                        ->deleteJson(route('admin.categories.delete', $category->id));
    $responseDel->assertStatus(200)
                ->assertJsonPath('success', true);

    expect(Category::find($category->id))->toBeNull();
});
