<?php

declare(strict_types=1);

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use App\Models\User;
use Livewire\Volt\Volt;

it('creates a self hosted service', function () {
    $user = User::factory()->create();

    $component = Volt::test('services.create')
        ->actingAs($user)
        ->set('name', 'My Node')
        ->set('type', SelfHostedServiceType::Mempool->value)
        ->set('url_clearnet', 'https://example.com')
                ->set('contact', ['url' => 'https://contact.example.com'])
        ->call('save');

    expect(SelfHostedService::where('name', 'My Node')->exists())->toBeTrue();

    $service = SelfHostedService::where('name', 'My Node')->first();
    expect($service->getFirstMedia('logo'))->toBeNull();
});
