<?php

use App\Models\Highscore;

it('returns all highscores ordered by satoshis desc on GET /api/highscores', function () {
    Highscore::factory()->create(['satoshis' => 100, 'achieved_at' => now()->subHours(1)]);
    Highscore::factory()->create(['satoshis' => 5000, 'achieved_at' => now()->subHours(2)]);
    Highscore::factory()->create(['satoshis' => 1000, 'achieved_at' => now()->subHours(3)]);

    $response = $this->getJson('/api/highscores');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect(collect($data)->pluck('satoshis')->all())->toBe([5000, 1000, 100]);
});

it('accepts a valid highscore submission', function () {
    $payload = [
        'npub' => 'npub1'.str_repeat('a', 58),
        'name' => 'Tester',
        'satoshis' => 1234,
        'blocks' => 5,
        'datetime' => now()->subDay()->toIso8601String(),
    ];

    $this->postJson('/api/highscores', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.satoshis', 1234)
        ->assertJsonPath('data.name', 'Tester');

    expect(Highscore::query()->where('npub', $payload['npub'])->exists())->toBeTrue();
});

it('rejects a highscore submission missing npub', function () {
    $this->postJson('/api/highscores', [
        'satoshis' => 1234,
        'blocks' => 5,
        'datetime' => now()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['npub']);
});

it('rejects a highscore submission with an npub that does not start with npub1', function () {
    $this->postJson('/api/highscores', [
        'npub' => 'nsec1'.str_repeat('a', 58),
        'satoshis' => 1234,
        'blocks' => 5,
        'datetime' => now()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['npub']);
});

it('rejects a highscore submission with negative satoshis', function () {
    $this->postJson('/api/highscores', [
        'npub' => 'npub1'.str_repeat('b', 58),
        'satoshis' => -10,
        'blocks' => 5,
        'datetime' => now()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['satoshis']);
});

it('rejects a highscore submission with an invalid datetime', function () {
    $this->postJson('/api/highscores', [
        'npub' => 'npub1'.str_repeat('c', 58),
        'satoshis' => 100,
        'blocks' => 5,
        'datetime' => 'not-a-date',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['datetime']);
});
