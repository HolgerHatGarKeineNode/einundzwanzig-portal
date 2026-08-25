<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\SelfHostedService;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Der Knopf zeigt, was die Policy erlaubt — nicht weniger.
 *
 * Nacharbeit aus P1: zwei Ansichten blendeten den Bearbeiten-Link ueber
 * `created_by === auth()->id()` aus, waehrend `LecturerPolicy::update()` und
 * `SelfHostedServicePolicy::update()` den Super-Admin kennen. Er hatte das Recht, sah
 * aber keinen Knopf — eine Luecke, die kein Autorisierungstest findet, weil serverseitig
 * alles stimmte.
 *
 * Gemessen wird an der ZIEL-URL des Links, nicht an einem Icon oder Beschriftungstext.
 * Flux inlined Icons als rohes SVG, der Name steht danach nirgends im HTML — ein Test
 * darauf meldet „kein Knopf" auch dort, wo einer steht (fail-open, in P2 belegt). Eine
 * Route-URL dagegen steht als Zeichenkette im Markup. Jeder Fall hat deshalb eine
 * Positiv- UND eine Negativprobe gegen dieselbe URL.
 */
function superAdminForLinkTest(): User
{
    Role::findOrCreate('super-admin');

    return User::factory()->create()->assignRole('super-admin');
}

/**
 * `lecturers.index` listet nur Dozenten, die einen Kurstermin in einer Stadt des
 * aktuellen Landes haben (`whereHas('coursesEvents.city.country')`). Ohne diese Kette
 * ist die Tabelle leer — und dann bestuende jede `assertDontSee`-Probe auch bei kaputtem
 * Code. Die Fixture ist deshalb Teil der Messung, nicht Beiwerk.
 */
function listedLecturer(?int $createdBy = null): Lecturer
{
    // `lecturers.created_by` ist NOT NULL — es gibt keinen herrenlosen Dozenten.
    $lecturer = Lecturer::factory()->create([
        'created_by' => $createdBy ?? User::factory()->create()->id,
    ]);

    $country = Country::query()->firstWhere('code', defaultCountrySegment())
        ?? Country::factory()->create(['code' => defaultCountrySegment()]);

    CourseEvent::factory()->create([
        'course_id' => Course::factory()->create(['lecturer_id' => $lecturer->id])->id,
        'city_id' => City::factory()->create(['country_id' => $country->id])->id,
        'from' => now()->addWeek(),
        'to' => now()->addWeek()->addHours(3),
    ]);

    return $lecturer;
}

it('shows the lecturer edit link to its creator', function () {
    $owner = actingAsUser();
    $lecturer = listedLecturer($owner->id);

    Livewire::test('lecturers.index')
        ->assertSee(route_with_country('lecturers.edit', ['lecturer' => $lecturer]), false);
});

it('shows the lecturer edit link to a super-admin on a foreign lecturer', function () {
    $lecturer = listedLecturer(User::factory()->create()->id);
    $this->actingAs(superAdminForLinkTest());

    Livewire::test('lecturers.index')
        ->assertSee(route_with_country('lecturers.edit', ['lecturer' => $lecturer]), false);
});

it('hides the lecturer edit link from a signed-in stranger', function () {
    $lecturer = listedLecturer(User::factory()->create()->id);
    actingAsUser();

    Livewire::test('lecturers.index')
        ->assertDontSee(route_with_country('lecturers.edit', ['lecturer' => $lecturer]), false);
});

it('offers a guest the login link instead of the lecturer edit link', function () {
    $lecturer = listedLecturer();

    Livewire::test('lecturers.index')
        ->assertDontSee(route_with_country('lecturers.edit', ['lecturer' => $lecturer]), false)
        ->assertSee(route('login'), false);
});

it('shows the service edit link to its creator', function () {
    $owner = actingAsUser();
    $service = SelfHostedService::factory()->create(['created_by' => $owner->id]);

    Livewire::test('services.landingpage', ['service' => $service])
        ->assertSee(route_with_country('services.edit', ['service' => $service]), false);
});

it('shows the service edit link to a super-admin on a foreign service', function () {
    $service = SelfHostedService::factory()->create(['created_by' => User::factory()->create()->id]);
    $this->actingAs(superAdminForLinkTest());

    Livewire::test('services.landingpage', ['service' => $service])
        ->assertSee(route_with_country('services.edit', ['service' => $service]), false);
});

it('hides the service edit link from a signed-in stranger', function () {
    $service = SelfHostedService::factory()->create(['created_by' => User::factory()->create()->id]);
    actingAsUser();

    Livewire::test('services.landingpage', ['service' => $service])
        ->assertDontSee(route_with_country('services.edit', ['service' => $service]), false);
});

/**
 * Der anonyme Dienst ist der Fall, den P1 ausdruecklich verschaerft hat: `created_by`
 * ist null, und das gab den Datensatz bisher fuer jeden Angemeldeten frei. Der Knopf
 * muss dieselbe Verschaerfung zeigen — sonst fuehrt er in einen 403.
 */
it('hides the service edit link on an anonymous service, but shows it to a super-admin', function () {
    $service = SelfHostedService::factory()->create(['created_by' => null]);
    $url = route_with_country('services.edit', ['service' => $service]);

    actingAsUser();
    Livewire::test('services.landingpage', ['service' => $service])->assertDontSee($url, false);

    $this->actingAs(superAdminForLinkTest());
    Livewire::test('services.landingpage', ['service' => $service])->assertSee($url, false);
});

/**
 * Knopf und Aktion muessen dieselbe Antwort geben. Der Loeschknopf lief bis P6 gegen
 * `created_by`, die Aktion ebenso — der Super-Admin sah nichts und durfte nichts. Jetzt
 * fragen beide `SelfHostedServicePolicy::delete()`.
 */
it('lets a super-admin actually delete a foreign service, not just see the button', function () {
    $service = SelfHostedService::factory()->create(['created_by' => User::factory()->create()->id]);
    $this->actingAs(superAdminForLinkTest());

    Livewire::test('services.landingpage', ['service' => $service])->call('delete');

    $this->assertDatabaseMissing('self_hosted_services', ['id' => $service->id]);
});

it('refuses the delete action to a signed-in stranger', function () {
    $service = SelfHostedService::factory()->create(['created_by' => User::factory()->create()->id]);
    actingAsUser();

    Livewire::test('services.landingpage', ['service' => $service])
        ->call('delete')
        ->assertStatus(403);

    $this->assertDatabaseHas('self_hosted_services', ['id' => $service->id]);
});

/*
|--------------------------------------------------------------------------
| P7 — die drei Kurs-Ansichten, die P6 gefunden und NICHT geaendert hat
|--------------------------------------------------------------------------
|
| Dieselbe Inline-Bedingung wie bei `lecturers/index`, aber mit einem Unterschied, der
| eine eigene Entscheidung brauchte: von den drei Stellen fragt nur EINE nach `update`
| auf dem Kurs. Die zweite fragt, wer einen TERMIN an diesen Kurs haengen darf, und die
| dritte fragte bisher die falsche Entitaet ueberhaupt.
*/

/**
 * `courses.index` listet nur Kurse, die einen Termin in einer Stadt des aktuellen Landes
 * haben (`whereHas('courseEvents.city.country')`). Ohne diese Kette ist die Tabelle leer
 * und jede `assertDontSee`-Probe waere auch bei kaputtem Code gruen.
 */
function listedCourse(?int $createdBy = null): Course
{
    $course = Course::factory()->create([
        'created_by' => $createdBy ?? User::factory()->create()->id,
    ]);

    $country = Country::query()->firstWhere('code', defaultCountrySegment())
        ?? Country::factory()->create(['code' => defaultCountrySegment()]);

    CourseEvent::factory()->create([
        'course_id' => $course->id,
        'city_id' => City::factory()->create(['country_id' => $country->id])->id,
        'from' => now()->addWeek(),
        'to' => now()->addWeek()->addHours(3),
    ]);

    return $course;
}

it('shows the course edit link to its creator', function () {
    $owner = actingAsUser();
    $course = listedCourse($owner->id);

    Livewire::test('courses.index')
        ->assertSee(route_with_country('courses.edit', ['course' => $course]), false);
});

it('shows the course edit link to a super-admin on a foreign course', function () {
    $course = listedCourse(User::factory()->create()->id);
    $this->actingAs(superAdminForLinkTest());

    Livewire::test('courses.index')
        ->assertSee(route_with_country('courses.edit', ['course' => $course]), false);
});

it('hides the course edit link from a signed-in stranger', function () {
    $course = listedCourse(User::factory()->create()->id);
    actingAsUser();

    Livewire::test('courses.index')
        ->assertDontSee(route_with_country('courses.edit', ['course' => $course]), false);
});

it('offers a guest the login link instead of the course edit link', function () {
    $course = listedCourse();

    Livewire::test('courses.index')
        ->assertDontSee(route_with_country('courses.edit', ['course' => $course]), false)
        ->assertSee(route('login'), false);
});

/**
 * Der Knopf „Neues Event erstellen" haengt an `CourseEventPolicy::createForCourse()`,
 * NICHT an `CoursePolicy::update()`.
 *
 * Heute liefern beide dasselbe Ergebnis — genau deshalb steht der Test hier. Er misst
 * die richtige Frage, solange die Antwort noch zufaellig uebereinstimmt; waere er auf
 * `update` gelegt, faende ihn niemand mehr, sobald die beiden auseinanderlaufen.
 */
it('shows the new-event link to the course owner and to a super-admin, but not to a stranger', function () {
    $owner = User::factory()->create();
    $course = listedCourse($owner->id);
    $url = route_with_country('courses.events.create', ['course' => $course]);

    $this->actingAs($owner);
    Livewire::test('courses.index')->assertSee($url, false);

    $this->actingAs(superAdminForLinkTest());
    Livewire::test('courses.index')->assertSee($url, false);

    actingAsUser();
    Livewire::test('courses.index')->assertDontSee($url, false);
});

/**
 * Der Knopf am Kurstermin fragt den TERMIN, nicht den Kurs — die Fundstelle, an der P6
 * nicht nur eine zu enge, sondern eine falsche Bedingung gefunden hat.
 *
 * Der entscheidende Fall ist der dritte: Kurs und Termin gehoeren VERSCHIEDENEN Leuten.
 * Vorher sah der Kurs-Eigentuemer einen Knopf, der ihn seit P1 in einen 403 fuehrt, und
 * der Termin-Ersteller sah keinen, obwohl ihm die Aktion offensteht. Nur dieser Fall
 * unterscheidet die richtige Policy von der falschen.
 */
it('binds the course event edit link to the event, not to the course', function () {
    $kursEigner = User::factory()->create();
    $terminEigner = User::factory()->create();

    $country = Country::query()->firstWhere('code', defaultCountrySegment())
        ?? Country::factory()->create(['code' => defaultCountrySegment()]);

    $course = Course::factory()->create(['created_by' => $kursEigner->id]);
    $event = CourseEvent::factory()->create([
        'course_id' => $course->id,
        'city_id' => City::factory()->create(['country_id' => $country->id])->id,
        'created_by' => $terminEigner->id,
        'from' => now()->addWeek(),
        'to' => now()->addWeek()->addHours(3),
    ]);

    $url = route_with_country('courses.events.edit', ['course' => $course, 'event' => $event]);

    // Der Termin-Ersteller darf — und sieht es jetzt auch.
    $this->actingAs($terminEigner);
    Livewire::test('courses.landingpage', ['course' => $course])->assertSee($url, false);

    // Der KURS-Eigentuemer darf seit P1 nicht mehr — und darf den Knopf deshalb nicht sehen.
    $this->actingAs($kursEigner);
    Livewire::test('courses.landingpage', ['course' => $course])->assertDontSee($url, false);

    // Der Super-Admin darf, ueber ChecksCreatorOwnership.
    $this->actingAs(superAdminForLinkTest());
    Livewire::test('courses.landingpage', ['course' => $course])->assertSee($url, false);

    // Und ein Fremder sieht nichts.
    actingAsUser();
    Livewire::test('courses.landingpage', ['course' => $course])->assertDontSee($url, false);
});

/**
 * Knopf und Aktion muessen dieselbe Antwort geben — sonst zeigt die Ansicht einen Weg in
 * einen 403. Gemessen an der echten Route, nicht an der Komponente.
 */
it('lets exactly the event creator through to the edit form the link points at', function () {
    $kursEigner = User::factory()->create();
    $terminEigner = User::factory()->create();

    $country = Country::query()->firstWhere('code', defaultCountrySegment())
        ?? Country::factory()->create(['code' => defaultCountrySegment()]);

    $course = Course::factory()->create(['created_by' => $kursEigner->id]);
    $event = CourseEvent::factory()->create([
        'course_id' => $course->id,
        'city_id' => City::factory()->create(['country_id' => $country->id])->id,
        'created_by' => $terminEigner->id,
    ]);

    $url = route_with_country('courses.events.edit', ['course' => $course, 'event' => $event]);

    $this->actingAs($terminEigner)->get($url)->assertSuccessful();
    $this->actingAs($kursEigner)->get($url)->assertForbidden();
});
