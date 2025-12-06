<?php

declare(strict_types=1);

use App\Models\SelfHostedService;
use Livewire\Volt\Volt;

it('renders services index', function () {
    SelfHostedService::factory()->count(2)->create();

    $component = Volt::test('services.index');

    $component->assertStatus(200)
        ->assertSee('Self Hosted Services');
});
