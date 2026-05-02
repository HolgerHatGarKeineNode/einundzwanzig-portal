<?php

use App\Jobs\FetchNostrProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('creates a new user and dispatches FetchNostrProfileJob when an unknown pubkey logs in', function () {
    Queue::fake();
    $pubkey = 'npub1'.str_repeat('z', 58);

    Livewire::test('auth.login')
        ->dispatch('nostrLoggedIn', pubkey: $pubkey)
        ->assertRedirect();

    $user = User::query()->where('nostr', $pubkey)->first();
    expect($user)->not->toBeNull()
        ->and((bool) $user->is_lecturer)->toBeTrue()
        ->and($user->email)->toEndWith('@portal.einundzwanzig.space');

    Queue::assertPushed(FetchNostrProfileJob::class);
    expect(auth()->id())->toBe($user->id);
});

it('logs in an existing user without creating a duplicate when their pubkey is already known', function () {
    Queue::fake();
    $pubkey = 'npub1'.str_repeat('a', 58);
    $existing = User::factory()->create(['nostr' => $pubkey]);

    Livewire::test('auth.login')
        ->dispatch('nostrLoggedIn', pubkey: $pubkey)
        ->assertRedirect();

    expect(User::query()->where('nostr', $pubkey)->count())->toBe(1);
    expect(auth()->id())->toBe($existing->id);
    Queue::assertPushed(FetchNostrProfileJob::class);
});
