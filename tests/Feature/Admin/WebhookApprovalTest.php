<?php

use App\Models\User;
use App\Models\WebhookSubscription;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #40 — board-gated admin view to approve/revoke webhook subscriptions
|--------------------------------------------------------------------------
|
| URLs use a literal public IP, never a hostname: the SSRF guard resolves DNS
| for a hostname, and a literal IP is checked directly, so these tests do not
| depend on real network access either way (same convention as
| tests/Feature/Settings/WebhooksTest.php).
|
*/

function boardMember(): User
{
    return User::factory()->create(['nostr' => config('einundzwanzig.board_members')[0]]);
}

it('is closed to guests', function () {
    Livewire::test('admin.webhooks')->assertStatus(403);
});

it('is closed to an authenticated user who is not on the board', function () {
    actingAsUser();

    Livewire::test('admin.webhooks')->assertStatus(403);
});

it('opens for a board member', function () {
    $this->actingAs(boardMember());

    Livewire::test('admin.webhooks')->assertOk();
});

it('lists pending subscriptions with owner, url and resources', function () {
    $this->actingAs(boardMember());

    $owner = User::factory()->create(['name' => 'Satoshi']);
    $pending = WebhookSubscription::factory()->pending()->create([
        'user_id' => $owner->id,
        'url' => 'https://1.1.1.1/hooks/incoming',
        'resources' => ['meetup', 'meetup-event'],
    ]);

    Livewire::test('admin.webhooks')
        ->assertSee('https://1.1.1.1/hooks/incoming')
        ->assertSee('Satoshi')
        ->assertSee('meetup, meetup-event');

    $ids = Livewire::test('admin.webhooks')->instance()->pending->pluck('id');
    expect($ids)->toContain($pending->id);
});

it('does not list an already approved subscription among the pending ones', function () {
    $this->actingAs(boardMember());

    $approved = WebhookSubscription::factory()->create(['approved_at' => now()]);

    $ids = Livewire::test('admin.webhooks')->instance()->pending->pluck('id');
    expect($ids)->not->toContain($approved->id);
});

it('lets a board member approve a pending subscription', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->create();

    Livewire::test('admin.webhooks')->call('approve', $subscription->id);

    expect($subscription->fresh()->approved_at)->not->toBeNull();
});

it('flips the owners settings page status from awaiting approval to active once approved', function () {
    $owner = User::factory()->create();
    $subscription = WebhookSubscription::factory()->pending()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);
    Livewire::test('settings.webhooks')->assertSee('Wartet auf Freigabe');

    $this->actingAs(boardMember());
    Livewire::test('admin.webhooks')->call('approve', $subscription->id);

    $this->actingAs($owner);
    Livewire::test('settings.webhooks')->assertSee('Aktiv');
});

it('refuses approval from a non-board user even by direct policy check', function () {
    $outsider = actingAsUser();
    $subscription = WebhookSubscription::factory()->pending()->create();

    expect($outsider->can('approve', $subscription))->toBeFalse();
    expect($subscription->fresh()->approved_at)->toBeNull();
});

it('lets a board member revoke an approved subscription without touching active or the row itself', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->create(['approved_at' => now(), 'active' => false]);

    Livewire::test('admin.webhooks')->call('revoke', $subscription->id);

    $fresh = $subscription->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->approved_at)->toBeNull()
        ->and($fresh->active)->toBeFalse();
});

it('round-trips approve then revoke back to pending', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->create();

    $component = Livewire::test('admin.webhooks');
    $component->call('approve', $subscription->id);
    expect($subscription->fresh()->approved_at)->not->toBeNull();

    $component->call('revoke', $subscription->id);
    expect($subscription->fresh()->approved_at)->toBeNull();
});

it('refuses revoke from a non-board user even by direct policy check', function () {
    $outsider = actingAsUser();
    $subscription = WebhookSubscription::factory()->create(['approved_at' => now()]);

    expect($outsider->can('revoke', $subscription))->toBeFalse();
    expect($subscription->fresh()->approved_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Approved but still blocked from delivery
|--------------------------------------------------------------------------
|
| ChangeRecorder::dispatchWebhooks() queues a delivery only for subscriptions
| matching WebhookSubscription::scopeEligibleForDelivery() — approved_at set,
| active true, disabled_at null. Approving does not touch `active` or
| `disabled_at`, so a paused or auto-disabled subscription stays out of that
| scope (and therefore receives nothing) even once approved.
|
*/

it('an approved-but-owner-paused subscription stays out of eligibleForDelivery', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->create(['active' => false]);

    Livewire::test('admin.webhooks')->call('approve', $subscription->id);

    expect(WebhookSubscription::query()->eligibleForDelivery()->whereKey($subscription->id)->exists())->toBeFalse();
});

it('an approved-but-system-disabled subscription stays out of eligibleForDelivery', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->disabled()->create();

    Livewire::test('admin.webhooks')->call('approve', $subscription->id);

    expect(WebhookSubscription::query()->eligibleForDelivery()->whereKey($subscription->id)->exists())->toBeFalse();
});

it('flags an approved-but-paused subscription in the approved list', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->create(['approved_at' => now(), 'active' => false]);

    Livewire::test('admin.webhooks')
        ->assertSee('Vom Besitzer pausiert', false);

    expect(Livewire::test('admin.webhooks')->instance()->stillBlocked($subscription))->not->toBeNull();
});

it('does not flag a fully eligible approved subscription', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->create(['approved_at' => now(), 'active' => true, 'disabled_at' => null]);

    expect(Livewire::test('admin.webhooks')->instance()->stillBlocked($subscription))->toBeNull();
});
