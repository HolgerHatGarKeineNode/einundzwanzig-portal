<?php

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\LibraryItem;

it('aborts when the merge target is missing', function () {
    Lecturer::factory()->create(['name' => 'Orphan']);

    $this->artisan('lecturers:cleanup', ['--force' => true])->assertExitCode(1);

    expect(Lecturer::query()->where('name', 'Orphan')->exists())->toBeTrue();
});

it('does nothing on a dry-run', function () {
    Lecturer::factory()->create(['name' => 'Einundzwanzig']);
    $empty = Lecturer::factory()->create();

    $this->artisan('lecturers:cleanup')->assertExitCode(0);

    expect(Lecturer::query()->find($empty->id))->not->toBeNull();
});

it('merges library items and deletes empty lecturers with --force', function () {
    $target = Lecturer::factory()->create(['name' => 'Einundzwanzig']);
    $empty = Lecturer::factory()->create();
    $item = LibraryItem::factory()->create(['lecturer_id' => $empty->id]);

    $this->artisan('lecturers:cleanup', ['--force' => true])
        ->expectsConfirmation('This permanently deletes lecturers on this database. Continue?', 'yes')
        ->assertExitCode(0);

    expect(Lecturer::query()->find($empty->id))->toBeNull();
    expect($item->fresh()->lecturer_id)->toBe($target->id);
});

it('keeps lecturers that have a course', function () {
    Lecturer::factory()->create(['name' => 'Einundzwanzig']);
    $withCourse = Lecturer::factory()->create();
    Course::factory()->create(['lecturer_id' => $withCourse->id]);

    $this->artisan('lecturers:cleanup', ['--force' => true])->assertExitCode(0);

    expect(Lecturer::query()->find($withCourse->id))->not->toBeNull();
});
