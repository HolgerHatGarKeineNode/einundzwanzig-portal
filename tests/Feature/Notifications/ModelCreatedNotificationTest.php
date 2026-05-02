<?php

use App\Models\Meetup;
use App\Models\User;
use App\Notifications\ModelCreatedNotification;
use Illuminate\Support\Facades\Notification;

it('sends a queued mail notification for a created model', function () {
    Notification::fake();

    $user = User::factory()->create();
    $meetup = Meetup::factory()->create();

    $user->notify(new ModelCreatedNotification($meetup, 'meetups'));

    Notification::assertSentTo($user, ModelCreatedNotification::class, function ($notification) use ($meetup) {
        return $notification->model->is($meetup) && $notification->resource === 'meetups';
    });
});

it('uses the mail channel', function () {
    $user = User::factory()->create();
    $notification = new ModelCreatedNotification(User::factory()->make(), 'users');

    expect($notification->via($user))->toBe(['mail']);
});
