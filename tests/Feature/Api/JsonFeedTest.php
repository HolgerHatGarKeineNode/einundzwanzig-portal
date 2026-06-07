<?php

use App\Models\User;

it('returns nostr-pubkeys in /api/nostrplebs', function () {
    User::factory()->create(['nostr' => 'npub1'.str_repeat('a', 58)]);
    User::factory()->create(['nostr' => 'npub1'.str_repeat('b', 58)]);
    User::factory()->create(['nostr' => null]);

    $response = $this->getJson('/api/nostrplebs');

    $response->assertSuccessful();
    expect($response->json())
        ->toHaveCount(2)
        ->each->toStartWith('npub1');
});
