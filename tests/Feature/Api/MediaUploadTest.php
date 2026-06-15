<?php

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
});

it('lets the owner upload a meetup logo', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => UploadedFile::fake()->image('logo.png', 512, 512),
    ]);

    $response->assertSuccessful();
    expect($response->json('data.logo'))->not->toBeEmpty();
    expect($meetup->fresh()->getFirstMedia('logo'))->not->toBeNull();
});

it('lets the owner upload a lecturer avatar', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $lecturer = Lecturer::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/lecturers/'.$lecturer->id.'/avatar', [
        'file' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
    ]);

    $response->assertSuccessful();
    expect($response->json('data.avatar'))->not->toBeEmpty();
    expect($lecturer->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

it('lets the owner upload a course logo', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $course = Course::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/courses/'.$course->id.'/logo', [
        'file' => UploadedFile::fake()->image('logo.webp', 512, 512),
    ]);

    $response->assertSuccessful();
    expect($response->json('data.logo'))->not->toBeEmpty();
    expect($course->fresh()->getFirstMedia('logo'))->not->toBeNull();
});

it('replaces the previous logo on re-upload (singleFile)', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => UploadedFile::fake()->image('first.png', 256, 256),
    ])->assertSuccessful();

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => UploadedFile::fake()->image('second.png', 256, 256),
    ])->assertSuccessful();

    expect($meetup->fresh()->getMedia('logo'))->toHaveCount(1);
});

it('rejects a guest uploading a logo', function () {
    $meetup = Meetup::factory()->create();

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => UploadedFile::fake()->image('logo.png', 256, 256),
    ])->assertUnauthorized();
});

it('forbids uploading a logo to a meetup owned by someone else', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => UploadedFile::fake()->image('logo.png', 256, 256),
    ])->assertForbidden();
});

it('rejects invalid uploads', function (UploadedFile $file) {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [
        'file' => $file,
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
})->with([
    'wrong mime' => fn () => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    'too large in size' => fn () => UploadedFile::fake()->image('huge.png', 256, 256)->size(6000),
    'too large in dimensions' => fn () => UploadedFile::fake()->image('giant.png', 4200, 4200),
]);

it('requires a file', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->postJson('/api/meetup/'.$meetup->id.'/logo', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});
