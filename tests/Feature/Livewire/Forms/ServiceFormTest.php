<?php

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use Livewire\Livewire;

it('mounts services.create when authenticated', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->assertStatus(200)
        ->assertSet('form.name', '')
        ->assertSet('form.anonymous', false);
});

it('persists a service when valid data is submitted', function () {
    $user = actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'Mein Mempool')
        ->set('form.type', SelfHostedServiceType::Mempool->value)
        ->set('form.url_clearnet', 'https://mempool.example.com')
        ->call('save')
        ->assertHasNoErrors();

    $service = SelfHostedService::query()->where('name', 'Mein Mempool')->first();

    expect($service)->not->toBeNull()
        ->and($service->type)->toBe(SelfHostedServiceType::Mempool)
        ->and($service->created_by)->toBe($user->id);
});

it('rejects submission when no URL or IP is provided', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'No Endpoint')
        ->set('form.type', SelfHostedServiceType::Other->value)
        ->call('save');

    expect(SelfHostedService::query()->where('name', 'No Endpoint')->exists())->toBeFalse();
});

it('validates required name and type fields', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->call('save')
        ->assertHasErrors([
            'form.name' => 'required',
            'form.type' => 'required',
        ]);
});

it('rejects invalid clearnet URLs', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'Bad URL')
        ->set('form.type', SelfHostedServiceType::Other->value)
        ->set('form.url_clearnet', 'not-a-valid-url')
        ->call('save')
        ->assertHasErrors(['form.url_clearnet' => 'url']);
});

it('rejects invalid IP addresses', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'Bad IP')
        ->set('form.type', SelfHostedServiceType::Other->value)
        ->set('form.ip', 'not-an-ip')
        ->call('save')
        ->assertHasErrors(['form.ip' => 'ip']);
});

it('rejects unknown service types — currently the value reaches the enum cast and triggers a ValueError (rules() in:... is not enforced)', function () {
    actingAsUser();

    expect(function () {
        Livewire::test('services.create')
            ->set('form.name', 'Bogus Type')
            ->set('form.type', 'NotARealType')
            ->set('form.url_clearnet', 'https://example.com')
            ->call('save');
    })->toThrow(ValueError::class);

    expect(SelfHostedService::query()->where('name', 'Bogus Type')->exists())->toBeFalse();
});

it('accepts every SelfHostedServiceType enum value as form.type', function (SelfHostedServiceType $type) {
    actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'Service '.$type->value)
        ->set('form.type', $type->value)
        ->set('form.url_clearnet', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();
})->with(SelfHostedServiceType::cases());

it('updates an existing service when authenticated as the creator', function () {
    $user = actingAsUser();
    $service = SelfHostedService::factory()->create([
        'created_by' => $user->id,
        'name' => 'Old Name',
    ]);

    Livewire::test('services.edit', ['service' => $service])
        ->set('form.name', 'New Name')
        ->set('form.type', SelfHostedServiceType::Mempool->value)
        ->set('form.url_clearnet', 'https://mempool.example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh()->name)->toBe('New Name');
});

it('marks anonymous service correctly via the anonymous flag', function () {
    actingAsUser();

    Livewire::test('services.create')
        ->set('form.name', 'Anon Service')
        ->set('form.type', SelfHostedServiceType::Other->value)
        ->set('form.url_clearnet', 'https://example.com')
        ->set('form.anonymous', true)
        ->call('save')
        ->assertHasNoErrors();

    $service = SelfHostedService::query()->where('name', 'Anon Service')->first();
    expect($service->anon)->toBeTrue();
});

it('initializes the form properly via setService when editing', function () {
    $user = actingAsUser();
    $service = SelfHostedService::factory()->create([
        'created_by' => $user->id,
        'name' => 'Filled Service',
        'type' => SelfHostedServiceType::Alby,
        'url_clearnet' => 'https://alby.example.com',
        'anon' => true,
    ]);

    Livewire::test('services.edit', ['service' => $service])
        ->assertSet('form.name', 'Filled Service')
        ->assertSet('form.type', SelfHostedServiceType::Alby->value)
        ->assertSet('form.url_clearnet', 'https://alby.example.com')
        ->assertSet('form.anonymous', true);
});
