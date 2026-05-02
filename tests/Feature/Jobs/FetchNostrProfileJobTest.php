<?php

use App\Jobs\FetchNostrProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('can be dispatched for a single user', function () {
    Queue::fake();
    $user = User::factory()->create(['nostr' => 'npub1'.str_repeat('a', 58)]);

    FetchNostrProfileJob::dispatch($user);

    Queue::assertPushed(
        FetchNostrProfileJob::class,
        fn (FetchNostrProfileJob $job) => $job->user?->id === $user->id,
    );
});

it('can be dispatched without a user (batch mode)', function () {
    Queue::fake();

    FetchNostrProfileJob::dispatch();

    Queue::assertPushed(
        FetchNostrProfileJob::class,
        fn (FetchNostrProfileJob $job) => $job->user === null,
    );
});

it('returns early when the supplied user has no nostr handle', function () {
    $user = User::factory()->create(['nostr' => null]);

    (new FetchNostrProfileJob($user))->handle();

    expect($user->refresh()->name)->not->toBeEmpty();
});
