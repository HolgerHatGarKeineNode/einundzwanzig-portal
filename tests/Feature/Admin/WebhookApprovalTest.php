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

/*
|--------------------------------------------------------------------------
| Rejecting — the third operator decision (P4 of the #36-#45 gap closure)
|--------------------------------------------------------------------------
|
| The pending list used to be hand-built as whereNull('approved_at'), which
| also matches a REJECTED subscription: a decision taken via webhook:reject
| reappeared in the queue as if nobody had looked at it, and the `Abgelehnt`
| state settings/webhooks.blade.php can render was unreachable from any UI.
| The queue now asks the model (scopePending: neither approved nor rejected),
| and the reject decision itself is available here.
|
*/

it('does not list a rejected subscription among the pending ones', function () {
    $this->actingAs(boardMember());

    $rejected = WebhookSubscription::factory()->rejected()->create();
    $stillPending = WebhookSubscription::factory()->pending()->create();

    $ids = Livewire::test('admin.webhooks')->instance()->pending->pluck('id');

    expect($ids)->not->toContain($rejected->id)
        ->and($ids)->toContain($stillPending->id);
});

it('does not show a rejected subscriptions url or owner in the pending list', function () {
    $this->actingAs(boardMember());

    $owner = User::factory()->create(['name' => 'Declined Dora']);
    WebhookSubscription::factory()->rejected()->create([
        'user_id' => $owner->id,
        'url' => 'https://1.1.1.1/hooks/declined',
    ]);

    Livewire::test('admin.webhooks')
        ->assertDontSee('https://1.1.1.1/hooks/declined')
        ->assertDontSee('Declined Dora')
        ->assertSee('Keine offenen Anfragen.');
});

it('lets a board member reject a pending subscription from the UI', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->create();

    Livewire::test('admin.webhooks')->call('reject', $subscription->id);

    $fresh = $subscription->fresh();

    expect($fresh->rejected_at)->not->toBeNull()
        ->and($fresh->approved_at)->toBeNull();

    // Gone from the queue for good — the point of the separate column.
    expect(Livewire::test('admin.webhooks')->instance()->pending->pluck('id'))
        ->not->toContain($subscription->id);
});

it('shows the owner the rejected state on their settings page after a UI rejection', function () {
    $owner = User::factory()->create();
    $subscription = WebhookSubscription::factory()->pending()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);
    Livewire::test('settings.webhooks')->assertSee('Wartet auf Freigabe');

    $this->actingAs(boardMember());
    Livewire::test('admin.webhooks')->call('reject', $subscription->id);

    $this->actingAs($owner);
    Livewire::test('settings.webhooks')
        ->assertSee('Abgelehnt')
        ->assertDontSee('Wartet auf Freigabe');
});

it('leaves every other rows active flag and approval untouched when one is rejected', function () {
    $this->actingAs(boardMember());

    $target = WebhookSubscription::factory()->pending()->create();
    $otherPending = WebhookSubscription::factory()->pending()->create(['active' => true]);
    $approvedButPaused = WebhookSubscription::factory()->create(['approved_at' => now(), 'active' => false]);

    Livewire::test('admin.webhooks')->call('reject', $target->id);

    expect($target->fresh()->rejected_at)->not->toBeNull()
        // The rejected row keeps its own pause switch: reject writes one column.
        ->and($target->fresh()->active)->toBe($target->active)
        ->and($otherPending->fresh()->rejected_at)->toBeNull()
        ->and($otherPending->fresh()->approved_at)->toBeNull()
        ->and($otherPending->fresh()->active)->toBeTrue()
        ->and($approvedButPaused->fresh()->approved_at)->not->toBeNull()
        ->and($approvedButPaused->fresh()->active)->toBeFalse();
});

it('is a no-op when the same subscription is rejected twice', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->pending()->create();

    $component = Livewire::test('admin.webhooks');
    $component->call('reject', $subscription->id);
    $firstDecision = $subscription->fresh()->rejected_at;

    $this->travel(5)->minutes();
    $component->call('reject', $subscription->id);

    expect($subscription->fresh()->rejected_at->equalTo($firstDecision))->toBeTrue();
});

it('refuses to reject an already approved subscription', function () {
    $this->actingAs(boardMember());
    $subscription = WebhookSubscription::factory()->create(['approved_at' => now()]);

    Livewire::test('admin.webhooks')->call('reject', $subscription->id);

    $fresh = $subscription->fresh();

    expect($fresh->rejected_at)->toBeNull()
        ->and($fresh->approved_at)->not->toBeNull();
});

it('refuses a rejection from a non-board user', function () {
    actingAsUser();
    $subscription = WebhookSubscription::factory()->pending()->create();

    Livewire::test('admin.webhooks')->assertStatus(403);

    expect($subscription->fresh()->rejected_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Click acknowledgement
|--------------------------------------------------------------------------
|
| Every action here runs a lookup, an authorization check and a save, then
| re-renders both lists. `wire:loading.attr="disabled"` is what the caller
| sets; Flux adds the matching `wire:target` from the button's own wire:click
| (vendor/livewire/flux/stubs/resources/views/flux/button/index.blade.php:58),
| so the loading state is scoped per row instead of dimming every button in
| the list. Setting the attribute ourselves also replaces Flux's own
| `data-flux-loading` spinner — ComponentAttributeBag::merge lets the caller's
| value win — which is the deliberate trade: not clickable beats animated.
|
*/

function buttonWithClick(string $html, string $call): ?string
{
    return preg_match('/<button[^>]*wire:click="'.preg_quote($call, '/').'"[^>]*>/', $html, $match) === 1
        ? $match[0]
        : null;
}

it('disables approve, reject and revoke while their own request is in flight', function () {
    $this->actingAs(boardMember());

    $pending = WebhookSubscription::factory()->pending()->create();
    $approved = WebhookSubscription::factory()->create(['approved_at' => now()]);

    $html = Livewire::test('admin.webhooks')->html();

    $calls = [
        "approve({$pending->id})",
        "reject({$pending->id})",
        "revoke({$approved->id})",
    ];

    foreach ($calls as $call) {
        $button = buttonWithClick($html, $call);

        expect($button)->not->toBeNull("no button found for wire:click=\"{$call}\"")
            ->and($button)->toContain('wire:loading.attr="disabled"')
            ->and($button)->toContain('wire:target="'.$call.'"');
    }
});
