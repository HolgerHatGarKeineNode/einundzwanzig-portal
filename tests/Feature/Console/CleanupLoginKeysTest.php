<?php

use App\Models\LoginKey;
use App\Models\User;

it('deletes login keys older than 1 day and keeps recent ones', function () {
    $user = User::factory()->create();

    $old = LoginKey::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $recent = LoginKey::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $this->artisan('loginkeys:cleanup')->assertExitCode(0);

    expect(LoginKey::query()->find($old->id))->toBeNull();
    expect(LoginKey::query()->find($recent->id))->not->toBeNull();
});

it('runs cleanly when no login keys exist', function () {
    $this->artisan('loginkeys:cleanup')->assertExitCode(0);
});
