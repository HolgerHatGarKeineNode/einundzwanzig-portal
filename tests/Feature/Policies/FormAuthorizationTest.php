<?php

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\SelfHostedService;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Beide Richtungen je Formular: was verboten ist UND was erlaubt bleibt.
 *
 * Die Erlaubnis-Richtung steht hier nicht aus Symmetrie. Eine Autorisierung, die nur
 * gegen das Verbotene gemessen wird, ist mit `abort(403)` in `mount()` trivial zu
 * bestehen — und sperrt dann auch den aus, der zuständig ist. Genau das fällt in einer
 * reinen Verbots-Messung nicht auf.
 *
 * Gemessen wird über `Livewire::test(...)` MIT Parametern, damit `mount()` echt läuft.
 * `->set()` schreibt Properties direkt und ginge an der Prüfung vorbei.
 */
function superAdminUser(): User
{
    Role::findOrCreate('super-admin');

    return User::factory()->create()->assignRole('super-admin');
}

it('refuses courses.create for a guest', function () {
    Livewire::test('courses.create')->assertStatus(403);
});

it('allows courses.create for any signed-in user', function () {
    actingAsUser();

    Livewire::test('courses.create')->assertStatus(200);
});

it('refuses courses.edit for someone who did not create the course', function () {
    $course = Course::factory()->create();
    actingAsUser();

    Livewire::test('courses.edit', ['course' => $course])->assertStatus(403);
});

it('allows courses.edit for the creator', function () {
    $owner = actingAsUser();
    $course = Course::factory()->create(['created_by' => $owner->id]);

    Livewire::test('courses.edit', ['course' => $course])->assertStatus(200);
});

it('allows courses.edit for a super-admin on a foreign course', function () {
    $course = Course::factory()->create();
    $this->actingAs(superAdminUser());

    Livewire::test('courses.edit', ['course' => $course])->assertStatus(200);
});

it('refuses a new course event to someone who does not own the course', function () {
    $course = Course::factory()->create();
    actingAsUser();

    Livewire::test('courses.create-edit-events', ['course' => $course])->assertStatus(403);
});

it('allows a new course event for the owner of the course', function () {
    $owner = actingAsUser();
    $course = Course::factory()->create(['created_by' => $owner->id]);

    Livewire::test('courses.create-edit-events', ['course' => $course])->assertStatus(200);
});

it('allows a new course event for a super-admin on a foreign course', function () {
    $course = Course::factory()->create();
    $this->actingAs(superAdminUser());

    Livewire::test('courses.create-edit-events', ['course' => $course])->assertStatus(200);
});

/**
 * Der Wechsel von der Kurs- auf die Termin-Berechtigung ist an dieser Stelle sichtbar:
 * wer den Termin angelegt hat, darf ihn ändern, auch ohne den Kurs zu besitzen.
 */
it('allows editing an existing course event for its creator, even without the course', function () {
    $creator = actingAsUser();
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create([
        'course_id' => $course->id,
        'created_by' => $creator->id,
    ]);

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->assertStatus(200);
});

it('refuses editing an existing course event to someone who is neither its creator nor a super-admin', function () {
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['course_id' => $course->id]);
    actingAsUser();

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->assertStatus(403);
});

it('refuses services.edit for someone who did not create the service', function () {
    $service = SelfHostedService::factory()->create();
    actingAsUser();

    Livewire::test('services.edit', ['service' => $service])->assertStatus(403);
});

/**
 * Die getroffene Entscheidung, gemessen: ein anonym eingestellter Dienst
 * (`created_by = null`) war bisher für JEDEN Angemeldeten editierbar.
 */
it('refuses services.edit on an anonymous service to a signed-in stranger', function () {
    $service = SelfHostedService::factory()->anonymous()->create();
    actingAsUser();

    expect($service->created_by)->toBeNull();

    Livewire::test('services.edit', ['service' => $service])->assertStatus(403);
});

it('allows services.edit for the creator', function () {
    $owner = actingAsUser();
    $service = SelfHostedService::factory()->create(['created_by' => $owner->id]);

    Livewire::test('services.edit', ['service' => $service])->assertStatus(200);
});

it('allows services.edit for a super-admin on a foreign service', function () {
    $service = SelfHostedService::factory()->create();
    $this->actingAs(superAdminUser());

    Livewire::test('services.edit', ['service' => $service])->assertStatus(200);
});

/**
 * Der Erbe der bestehenden anonymen Datensätze: der Super-Admin. Ohne diesen Test wäre
 * die Verschärfung oben nicht von einem Totalausfall zu unterscheiden.
 */
it('allows services.edit on an anonymous service for a super-admin', function () {
    $service = SelfHostedService::factory()->anonymous()->create();
    $this->actingAs(superAdminUser());

    Livewire::test('services.edit', ['service' => $service])->assertStatus(200);
});

it('refuses lecturers.edit for someone who did not create the lecturer', function () {
    $lecturer = Lecturer::factory()->create();
    actingAsUser();

    Livewire::test('lecturers.edit', ['lecturer' => $lecturer])->assertStatus(403);
});

it('allows lecturers.edit for the creator', function () {
    $owner = actingAsUser();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    Livewire::test('lecturers.edit', ['lecturer' => $lecturer])->assertStatus(200);
});

/**
 * Der Super-Admin-Bypass lag seit jeher in ChecksCreatorOwnership — die Inline-Bedingung
 * im Formular fragte ihn nur nie ab. Genau das misst dieser Test.
 */
it('allows lecturers.edit for a super-admin on a foreign lecturer', function () {
    $lecturer = Lecturer::factory()->create();
    $this->actingAs(superAdminUser());

    Livewire::test('lecturers.edit', ['lecturer' => $lecturer])->assertStatus(200);
});
