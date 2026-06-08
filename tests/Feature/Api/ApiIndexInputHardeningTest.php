<?php

use App\Models\Course;
use App\Models\Venue;

it('drops non-numeric selected values on GET /api/courses instead of erroring', function () {
    $course = Course::factory()->create();

    $response = $this->getJson('/api/courses?selected[]='.$course->id.'&selected[]=foo');

    $response->assertSuccessful();
    expect(collect($response->json())->pluck('id')->all())->toBe([$course->id]);
});

it('casts a non-numeric user_id to an empty filter on GET /api/courses', function () {
    Course::factory()->create();

    $this->getJson('/api/courses?user_id=abc')
        ->assertSuccessful()
        ->assertJsonCount(0);
});

it('tolerates a non-array selected value on GET /api/venues without a 500', function () {
    Venue::factory()->create();

    $this->getJson('/api/venues?selected=foo')
        ->assertSuccessful()
        ->assertJsonCount(0);
});
