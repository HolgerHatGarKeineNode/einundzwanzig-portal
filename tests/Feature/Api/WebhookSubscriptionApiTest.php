<?php

use App\Models\User;
use App\Models\WebhookSubscription;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Issue #36 — self-service webhook subscriptions
|--------------------------------------------------------------------------
|
| URLs use literal public/private IPs, never a hostname: the SSRF guard
| resolves DNS for a hostname, and a literal IP is checked directly, so these
| tests do not depend on real network access either way.
|
*/

it('rejects a guest', function () {
    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://1.1.1.1/hook',
        'resources' => ['meetup'],
    ]);

    $response->assertUnauthorized();
});

it('lets an authenticated user create a subscription and returns the secret exactly once', function () {
    config()->set('einundzwanzig.webhooks.require_approval', false);
    Sanctum::actingAs($user = User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://1.1.1.1/hook',
        'resources' => ['meetup', 'meetup-event'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.url', 'https://1.1.1.1/hook')
        ->assertJsonPath('data.status', 'active');

    $secret = $response->json('data.secret');

    expect($secret)->toBeString()
        ->and(strlen($secret))->toBeGreaterThanOrEqual(64);

    $subscription = WebhookSubscription::query()->where('user_id', $user->id)->sole();

    expect($subscription->secret)->toBe($secret);

    $index = $this->getJson('/api/webhook-subscriptions');

    $index->assertSuccessful()
        ->assertJsonMissingPath('data.0.secret');
});

it('starts a subscription pending when approval is required, by default', function () {
    Sanctum::actingAs(User::factory()->create());

    expect(config('einundzwanzig.webhooks.require_approval'))->toBeTrue();

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://1.1.1.1/hook',
        'resources' => ['meetup'],
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');

    $subscription = WebhookSubscription::query()->sole();

    expect($subscription->approved_at)->toBeNull();
});

it('rejects a non-https url', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'http://1.1.1.1/hook',
        'resources' => ['meetup'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['url']);
});

it('rejects a url resolving to a loopback address', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://127.0.0.1/hook',
        'resources' => ['meetup'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['url']);
});

it('rejects a url resolving to the cloud metadata address', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://169.254.169.254/latest/meta-data',
        'resources' => ['meetup'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['url']);
});

it('rejects a url resolving to an RFC1918 private address', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://10.0.0.5/hook',
        'resources' => ['meetup'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['url']);
});

it('rejects a resource outside the allowed list', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://1.1.1.1/hook',
        // Recorded by ChangeRecorder, but not offered to subscribers yet.
        'resources' => ['city'],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['resources.0']);
});

it('requires at least one resource', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/webhook-subscriptions', [
        'url' => 'https://1.1.1.1/hook',
        'resources' => [],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['resources']);
});

it('returns only own subscriptions in the index', function () {
    Sanctum::actingAs($user = User::factory()->create());

    WebhookSubscription::factory()->count(2)->create(['user_id' => $user->id]);
    WebhookSubscription::factory()->create();

    $response = $this->getJson('/api/webhook-subscriptions');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
});

it('lets the owner update url, resources and pause delivery', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $subscription = WebhookSubscription::factory()->create(['user_id' => $user->id]);

    $response = $this->patchJson("/api/webhook-subscriptions/{$subscription->id}", [
        'url' => 'https://1.1.1.1/new-hook',
        'active' => false,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.url', 'https://1.1.1.1/new-hook')
        ->assertJsonPath('data.status', 'paused');
});

it('forbids updating someone elses subscription', function () {
    $owner = User::factory()->create();
    $subscription = WebhookSubscription::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/webhook-subscriptions/{$subscription->id}", [
        'active' => false,
    ]);

    $response->assertForbidden();
});

it('clears the disabled state and failure count when the owner resumes it', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $subscription = WebhookSubscription::factory()->disabled()->create(['user_id' => $user->id]);

    $response = $this->patchJson("/api/webhook-subscriptions/{$subscription->id}", [
        'active' => true,
    ]);

    $response->assertSuccessful()->assertJsonPath('data.status', 'active');

    expect($subscription->fresh())
        ->disabled_at->toBeNull()
        ->consecutive_failures->toBe(0);
});

it('lets the owner delete a subscription', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $subscription = WebhookSubscription::factory()->create(['user_id' => $user->id]);

    $response = $this->deleteJson("/api/webhook-subscriptions/{$subscription->id}");

    $response->assertNoContent();
    expect(WebhookSubscription::query()->find($subscription->id))->toBeNull();
});

it('forbids deleting someone elses subscription', function () {
    $owner = User::factory()->create();
    $subscription = WebhookSubscription::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->deleteJson("/api/webhook-subscriptions/{$subscription->id}");

    $response->assertForbidden();
    expect(WebhookSubscription::query()->find($subscription->id))->not->toBeNull();
});
