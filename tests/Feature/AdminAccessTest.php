<?php

use App\Enums\UserRole;
use App\Models\User;

test('cruds, tinker and admin printed routes require authentication', function () {
    $this->get(route('cruds'))->assertRedirect(route('login'));
    $this->get(route('tinker'))->assertRedirect(route('login'));
    $this->get(route('admin.printed'))->assertRedirect(route('login'));
});

test('staff user cannot access cruds, tinker or admin printed', function () {
    $user = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($user);

    $this->get(route('cruds'))->assertForbidden();
    $this->get(route('tinker'))->assertForbidden();
    $this->get(route('admin.printed'))->assertForbidden();
});

test('admin user can access cruds, tinker and admin printed', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $this->get(route('cruds'))->assertSuccessful();
    $this->get(route('tinker'))->assertSuccessful();
    $this->get(route('admin.printed'))->assertSuccessful();
});
