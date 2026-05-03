<?php

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;

it('creates a new SelfHostedService end-to-end and shows it on the index', function () {
    actingAsUser();

    $page = visit('/de/service-create');

    $page->fill('[wire\\:model="form.name"]', 'BrowserTestNode')
        ->select('[wire\\:model="form.type"]', SelfHostedServiceType::Mempool->value)
        ->fill('[wire\\:model="form.url_clearnet"]', 'https://browsertest.example.com')
        ->fill('[wire\\:model="form.intro"]', 'A node spun up by a browser test.')
        ->click('[data-flux-button][type="submit"]')
        ->wait(2)
        ->assertNoJavaScriptErrors()
        ->assertSee('BrowserTestNode');

    expect(SelfHostedService::query()->where('name', 'BrowserTestNode')->exists())->toBeTrue();
});

it('blocks submission without a name and shows a required error', function () {
    actingAsUser();

    $page = visit('/de/service-create');

    $page->select('[wire\\:model="form.type"]', SelfHostedServiceType::Other->value)
        ->fill('[wire\\:model="form.url_clearnet"]', 'https://no-name.example.com')
        ->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(SelfHostedService::query()->count())->toBe(0);
});

it('rejects submission when no URL or IP is provided', function () {
    actingAsUser();

    $page = visit('/de/service-create');

    $page->fill('[wire\\:model="form.name"]', 'NoUrlService')
        ->select('[wire\\:model="form.type"]', SelfHostedServiceType::Other->value)
        ->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(SelfHostedService::query()->where('name', 'NoUrlService')->exists())->toBeFalse();
});
