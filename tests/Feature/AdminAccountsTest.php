<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login when visiting admin accounts page', function () {
    $response = $this->get(route('admin.accounts'));
    $response->assertRedirect(route('login'));
});

test('non-admin users receive 403 when visiting admin accounts page', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($user);

    $response = $this->get(route('admin.accounts'));
    $response->assertForbidden();
});

test('admin can visit accounts page and see list of users', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);
    $this->actingAs($admin);

    $response = $this->get(route('admin.accounts'));
    $response->assertOk();
    $response->assertSee('User accounts');
    $response->assertSee('Other User');
    $response->assertSee('other@example.com');
});

test('admin can change another user role', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($admin);

    Livewire::test('pages::accounts')
        ->set('roleSelections.'.$other->id, 'doc')
        ->assertOk();

    expect($other->fresh()->role)->toBe(UserRole::Doc);
});

test('admin cannot change their own role away from admin', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::accounts')
        ->set('roleSelections.'.$admin->id, 'staff')
        ->assertOk();

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});
