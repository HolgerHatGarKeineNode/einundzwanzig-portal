<?php

use App\Models\LibraryItem;
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

it('returns bindle-type library items in /api/bindles', function () {
    LibraryItem::factory()->create(['type' => 'bindle', 'name' => 'My Bindle']);
    LibraryItem::factory()->create(['type' => 'article', 'name' => 'My Article']);

    $response = $this->getJson('/api/bindles');

    $response->assertSuccessful();
    $names = collect($response->json())->pluck('name');
    expect($names->all())
        ->toContain('My Bindle')
        ->not->toContain('My Article');
});
