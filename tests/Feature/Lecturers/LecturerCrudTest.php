<?php

use App\Models\Lecturer;
use Livewire\Livewire;

it('creates a Lecturer with valid data', function () {
    actingAsUser();

    Livewire::test('lecturers.create')
        ->set('name', 'Satoshi Nakamoto')
        ->call('createLecturer')
        ->assertHasNoErrors();

    expect(Lecturer::query()->where('name', 'Satoshi Nakamoto')->exists())->toBeTrue();
});

it('rejects lecturer creation without name', function () {
    actingAsUser();

    Livewire::test('lecturers.create')
        ->call('createLecturer')
        ->assertHasErrors(['name' => 'required']);
});

it('rejects lecturer creation with duplicate name', function () {
    Lecturer::factory()->create(['name' => 'Already Exists']);
    actingAsUser();

    Livewire::test('lecturers.create')
        ->set('name', 'Already Exists')
        ->call('createLecturer')
        ->assertHasErrors(['name' => 'unique']);
});

it('rejects lecturer creation with invalid website URL', function () {
    actingAsUser();

    Livewire::test('lecturers.create')
        ->set('name', 'Bad URL Lecturer')
        ->set('website', 'not-a-url')
        ->call('createLecturer')
        ->assertHasErrors(['website' => 'url']);
});

it('updates an existing lecturer', function () {
    $lecturer = Lecturer::factory()->create(['name' => 'Old Name']);
    actingAsUser();

    Livewire::test('lecturers.edit', ['lecturer' => $lecturer])
        ->set('name', 'New Name')
        ->call('updateLecturer')
        ->assertHasNoErrors();

    expect($lecturer->refresh()->name)->toBe('New Name');
});

it('redirects guests when accessing lecturer-create', function () {
    $this->get('/de/lecturer-create')->assertRedirect(route('login'));
});
