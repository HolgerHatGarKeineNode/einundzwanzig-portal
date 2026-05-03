<?php

use App\Models\Lecturer;

it('creates a new lecturer end-to-end with valid data', function () {
    actingAsUser();

    $page = visit('/de/lecturer-create');

    $page->fill('[wire\\:model="name"]', 'BrowserTester Saylor')
        ->fill('[wire\\:model="subtitle"]', 'Browser Test Subject')
        ->fill('[wire\\:model="intro"]', 'A short intro line.')
        ->click('[data-flux-button][type="submit"]')
        ->wait(2)
        ->assertNoJavaScriptErrors();

    expect(Lecturer::query()->where('name', 'BrowserTester Saylor')->exists())->toBeTrue();
});

it('shows a required error when submitting without a lecturer name', function () {
    actingAsUser();

    $page = visit('/de/lecturer-create');

    $page->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Lecturer::query()->count())->toBe(0);
});

it('rejects creation when the lecturer name already exists', function () {
    actingAsUser();
    Lecturer::factory()->create(['name' => 'Existing Saylor']);

    $page = visit('/de/lecturer-create');

    $page->fill('[wire\\:model="name"]', 'Existing Saylor')
        ->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Lecturer::query()->where('name', 'Existing Saylor')->count())->toBe(1);
});

it('rejects creation with an invalid website URL', function () {
    actingAsUser();

    $page = visit('/de/lecturer-create');

    $page->fill('[wire\\:model="name"]', 'Bad URL Lecturer')
        ->fill('[wire\\:model="website"]', 'not-a-url')
        ->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Lecturer::query()->where('name', 'Bad URL Lecturer')->exists())->toBeFalse();
});
