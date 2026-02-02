<?php

use App\Http\Controllers\Api\HighscoreController;
use App\Models\Highscore;
use Carbon\CarbonImmutable;

test('highscore submission is accepted and stored', function () {
    $payload = [
        'npub' => 'npub1examplehighscorevalue',
        'name' => 'Player One',
        'satoshis' => 2100,
        'blocks' => 5,
        'datetime' => CarbonImmutable::now()->subMinute()->toIso8601String(),
    ];

    $response = $this->postJson(route('api.highscores.store'), $payload);

    $response->assertAccepted()
        ->assertJson([
            'message' => 'Highscore received',
            'data' => [
                'npub' => $payload['npub'],
                'name' => $payload['name'],
                'satoshis' => $payload['satoshis'],
                'blocks' => $payload['blocks'],
                'datetime' => CarbonImmutable::parse($payload['datetime'])->toIso8601String(),
            ],
        ]);

    $this->assertDatabaseHas('highscores', [
        'npub' => $payload['npub'],
        'name' => $payload['name'],
        'satoshis' => $payload['satoshis'],
        'blocks' => $payload['blocks'],
        'achieved_at' => CarbonImmutable::parse($payload['datetime'])->toDateTimeString(),
    ]);
});

test('highscore submission updates existing attempt for same npub and datetime', function () {
    $datetime = CarbonImmutable::now()->subMinutes(10)->toIso8601String();

    Highscore::factory()->create([
        'npub' => 'npub1examplehighscorevalue',
        'name' => 'Player One',
        'satoshis' => 1000,
        'blocks' => 3,
        'achieved_at' => $datetime,
    ]);

    $response = $this->postJson(route('api.highscores.store'), [
        'npub' => 'npub1examplehighscorevalue',
        'name' => 'Player One Updated',
        'satoshis' => 2500,
        'blocks' => 7,
        'datetime' => $datetime,
    ]);

    $response->assertAccepted();

    $this->assertDatabaseHas('highscores', [
        'npub' => 'npub1examplehighscorevalue',
        'name' => 'Player One Updated',
        'satoshis' => 2500,
        'blocks' => 7,
        'achieved_at' => CarbonImmutable::parse($datetime)->toDateTimeString(),
    ]);
    $this->assertSame(1, Highscore::query()->count());
});

test('missing name is fetched from nostr when available', function () {
    $fetchedName = 'Fetched Player';

    $controllerMock = \Mockery::mock(HighscoreController::class)->makePartial();
    $controllerMock->shouldAllowMockingProtectedMethods();
    $controllerMock->shouldReceive('fetchNostrName')->once()->andReturn($fetchedName);

    app()->instance(HighscoreController::class, $controllerMock);

    $payload = [
        'npub' => 'npub1fetchnamevalue',
        'satoshis' => 1337,
        'blocks' => 2,
        'datetime' => CarbonImmutable::now()->subMinute()->toIso8601String(),
    ];

    $response = $this->postJson(route('api.highscores.store'), $payload);

    $response->assertAccepted()
        ->assertJsonPath('data.name', $fetchedName);

    $this->assertDatabaseHas('highscores', [
        'npub' => $payload['npub'],
        'name' => $fetchedName,
        'satoshis' => $payload['satoshis'],
        'blocks' => $payload['blocks'],
    ]);
});

test('highscore submission does not clear existing name when omitted', function () {
    $datetime = CarbonImmutable::now()->subMinutes(15);

    Highscore::factory()->create([
        'npub' => 'npub1keepname',
        'name' => 'Keep Name',
        'satoshis' => 1234,
        'blocks' => 2,
        'achieved_at' => $datetime,
    ]);

    $response = $this->postJson(route('api.highscores.store'), [
        'npub' => 'npub1keepname',
        'satoshis' => 2000,
        'blocks' => 4,
        'datetime' => $datetime->toIso8601String(),
    ]);

    $response->assertAccepted();

    $this->assertDatabaseHas('highscores', [
        'npub' => 'npub1keepname',
        'name' => 'Keep Name',
        'satoshis' => 2000,
        'blocks' => 4,
        'achieved_at' => $datetime->toDateTimeString(),
    ]);
});

test('highscores are returned sorted by satoshis', function () {
    $lowest = Highscore::factory()->create([
        'npub' => 'npub1low',
        'name' => 'Low Player',
        'satoshis' => 500,
        'blocks' => 2,
        'achieved_at' => CarbonImmutable::parse('2025-01-01T00:00:00Z'),
    ]);
    $middle = Highscore::factory()->create([
        'npub' => 'npub1mid',
        'name' => 'Mid Player',
        'satoshis' => 1500,
        'blocks' => 4,
        'achieved_at' => CarbonImmutable::parse('2025-01-02T00:00:00Z'),
    ]);
    $highest = Highscore::factory()->create([
        'npub' => 'npub1high',
        'name' => 'High Player',
        'satoshis' => 3000,
        'blocks' => 6,
        'achieved_at' => CarbonImmutable::parse('2025-01-03T00:00:00Z'),
    ]);

    $response = $this->getJson(route('api.highscores.index'));

    $response->assertOk();

    $response->assertJsonPath('data.0.npub', $highest->npub)
        ->assertJsonPath('data.0.satoshis', $highest->satoshis)
        ->assertJsonPath('data.0.name', $highest->name)
        ->assertJsonPath('data.1.npub', $middle->npub)
        ->assertJsonPath('data.2.npub', $lowest->npub);
});

dataset('invalidHighscorePayloads', [
    'missing npub' => fn () => [
        [
            'satoshis' => 1000,
            'blocks' => 3,
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['npub'],
    ],
    'invalid npub prefix' => fn () => [
        [
            'npub' => 'wrong-npub',
            'satoshis' => 1000,
            'blocks' => 3,
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['npub'],
    ],
    'non integer satoshis' => fn () => [
        [
            'npub' => 'npub1examplehighscorevalue',
            'satoshis' => 'not-an-int',
            'blocks' => 3,
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['satoshis'],
    ],
    'negative satoshis' => fn () => [
        [
            'npub' => 'npub1examplehighscorevalue',
            'satoshis' => -1,
            'blocks' => 3,
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['satoshis'],
    ],
    'non integer blocks' => fn () => [
        [
            'npub' => 'npub1examplehighscorevalue',
            'satoshis' => 1000,
            'blocks' => 'not-an-int',
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['blocks'],
    ],
    'negative blocks' => fn () => [
        [
            'npub' => 'npub1examplehighscorevalue',
            'satoshis' => 1000,
            'blocks' => -1,
            'datetime' => CarbonImmutable::now()->toIso8601String(),
        ],
        ['blocks'],
    ],
    'invalid datetime' => fn () => [
        [
            'npub' => 'npub1examplehighscorevalue',
            'satoshis' => 1000,
            'blocks' => 3,
            'datetime' => 'not a date',
        ],
        ['datetime'],
    ],
]);

it('validates highscore payload', function (array $payload, array $invalidFields) {
    $response = $this->postJson(route('api.highscores.store'), $payload);

    $response->assertUnprocessable()
        ->assertInvalid($invalidFields);
})->with('invalidHighscorePayloads');
