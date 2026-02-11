<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\SelfHostedService;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $this->country = Country::factory()->create();
    $this->city = City::factory()->for($this->country)->create();
});

describe('Meetup Edit Component', function () {
    beforeEach(function () {
        $this->meetup = Meetup::factory()->for($this->city)->create([
            'created_by' => $this->user->id,
        ]);
    });

    it('loads meetup edit page correctly with locked properties', function () {
        Livewire::actingAs($this->user)
            ->test('meetups.edit', ['meetup' => $this->meetup])
            ->assertSet('created_by', $this->meetup->created_by)
            ->assertSet('created_at', $this->meetup->created_at->format('Y-m-d H:i:s'))
            ->assertSet('updated_at', $this->meetup->updated_at->format('Y-m-d H:i:s'));
    });

    it('throws exception when tampering with locked created_by property', function () {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('meetups.edit', ['meetup' => $this->meetup])
            ->set('created_by', 999);
    });

    it('throws exception when tampering with locked created_at property', function () {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('meetups.edit', ['meetup' => $this->meetup])
            ->set('created_at', '2020-01-01 00:00:00');
    });

    it('throws exception when tampering with locked updated_at property', function () {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('meetups.edit', ['meetup' => $this->meetup])
            ->set('updated_at', '2020-01-01 00:00:00');
    });

    it('can still update non-locked properties', function () {
        Livewire::actingAs($this->user)
            ->test('meetups.edit', ['meetup' => $this->meetup])
            ->set('name', 'Updated Meetup Name')
            ->set('community', 'einundzwanzig')
            ->call('updateMeetup')
            ->assertHasNoErrors();

        $this->meetup->refresh();
        expect($this->meetup->name)->toBe('Updated Meetup Name');
    });
});

describe('Meetup Create-Edit Events Component', function () {
    beforeEach(function () {
        $this->meetup = Meetup::factory()->for($this->city)->create();
    });

    it('has locked country property', function () {
        Livewire::actingAs($this->user)
            ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
            ->assertSet('country', 'de');
    });

    it('throws exception when tampering with locked country property', function () {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
            ->set('country', 'us');
    });
});

describe('Services Create Component', function () {
    it('has locked country property', function () {
        Livewire::actingAs($this->user)
            ->test('services.create')
            ->assertSet('country', 'de');
    });

    it('throws exception when tampering with locked country property', function () {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('services.create')
            ->set('country', 'us');
    });
});

describe('ServiceForm Locked Properties', function () {
    beforeEach(function () {
        // Create service with the current user as creator
        $this->service = SelfHostedService::factory()->create([
            'created_by' => $this->user->id,
            'anon' => false,
        ]);
    });

    it('has locked service property in edit component', function () {
        Livewire::actingAs($this->user)
            ->test('services.edit', ['service' => $this->service])
            ->assertSet('form.service.id', $this->service->id);
    });

    it('throws exception when tampering with locked service model in form', function () {
        $anotherService = SelfHostedService::factory()->create([
            'created_by' => $this->user->id,
            'anon' => false,
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test('services.edit', ['service' => $this->service])
            ->set('form.service', $anotherService);
    });
});
