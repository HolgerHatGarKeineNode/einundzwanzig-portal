<?php

use App\Models\Course;

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

it('tolerates a non-array selected value without a 500', function () {
    // Used to run against /api/venues, which no longer exists. The hardening it covers is
    // a scalar where the endpoint expects a list — worth keeping wherever `selected` lives.
    Course::factory()->create();

    $this->getJson('/api/courses?selected=foo')
        ->assertSuccessful()
        ->assertJsonCount(0);
});
