<?php

use App\Enums\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('admin users are redirected to the admin dashboard', function () {
    $user = User::factory()->create([
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('admin.dashboard'));
});

test('teacher users are redirected to the teacher dashboard', function () {
    $user = User::factory()->create([
        'role' => Role::Teacher,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('teacher.dashboard'));
});
