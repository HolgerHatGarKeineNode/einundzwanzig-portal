<?php

use App\Models\Course;
use App\Models\Lecturer;
use Livewire\Livewire;

beforeEach(function () {
    $this->lecturer = Lecturer::factory()->create();
});

it('creates a Course with valid data', function () {
    actingAsUser();

    Livewire::test('courses.create')
        ->set('name', 'Bitcoin 101')
        ->set('lecturer_id', $this->lecturer->id)
        ->call('createCourse')
        ->assertHasNoErrors();

    expect(Course::query()->where('name', 'Bitcoin 101')->exists())->toBeTrue();
});

it('rejects course creation without a name', function () {
    actingAsUser();

    Livewire::test('courses.create')
        ->set('lecturer_id', $this->lecturer->id)
        ->call('createCourse')
        ->assertHasErrors(['name' => 'required']);
});

it('rejects course creation without a lecturer', function () {
    actingAsUser();

    Livewire::test('courses.create')
        ->set('name', 'Course Without Lecturer')
        ->call('createCourse')
        ->assertHasErrors(['lecturer_id' => 'required']);
});

it('rejects course creation with non-existent lecturer_id', function () {
    actingAsUser();

    Livewire::test('courses.create')
        ->set('name', 'Bad Lecturer Course')
        ->set('lecturer_id', 999999)
        ->call('createCourse')
        ->assertHasErrors(['lecturer_id' => 'exists']);
});

it('updates an existing course as its creator', function () {
    $owner = actingAsUser();
    $course = Course::factory()->create([
        'name' => 'Old Name',
        'lecturer_id' => $this->lecturer->id,
        'created_by' => $owner->id,
    ]);

    Livewire::test('courses.edit', ['course' => $course])
        ->set('name', 'New Name')
        ->set('lecturer_id', $this->lecturer->id)
        ->call('updateCourse')
        ->assertHasNoErrors();

    expect($course->refresh()->name)->toBe('New Name');
});

it('redirects guests when accessing course-create', function () {
    $this->get('/de/course-create')->assertRedirect(route('login'));
});
