<?php

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #36 — self-service webhooks settings page
|--------------------------------------------------------------------------
|
| URLs use a literal public IP, never a hostname: the SSRF guard resolves
| DNS for a hostname, and a literal IP is checked directly, so these tests
| do not depend on real network access either way.
|
*/

it('mounts the webhooks page when authenticated', function () {
    actingAsUser();

    Livewire::test('settings.webhooks')->assertStatus(200);
});

it('creates a subscription and reveals the secret once, regardless of reveal_secret', function () {
    $user = actingAsUser();

    Livewire::test('settings.webhooks')
        ->set('url', 'https://1.1.1.1/hook')
        ->set('resources', ['meetup'])
        ->call('createSubscription')
        ->assertHasNoErrors()
        ->assertDispatched('webhook-created')
        ->assertSet('url', '')
        ->assertSet('plainTextSecret', fn ($secret) => is_string($secret) && strlen($secret) >= 64);

    $subscription = WebhookSubscription::query()->where('user_id', $user->id)->sole();

    expect($subscription->reveal_secret)->toBeFalse();
});

it('rejects an unreachable/private target url', function () {
    actingAsUser();

    Livewire::test('settings.webhooks')
        ->set('url', 'https://127.0.0.1/hook')
        ->set('resources', ['meetup'])
        ->call('createSubscription')
        ->assertHasErrors(['url']);
});

it('requires at least one resource', function () {
    actingAsUser();

    Livewire::test('settings.webhooks')
        ->set('url', 'https://1.1.1.1/hook')
        ->set('resources', [])
        ->call('createSubscription')
        ->assertHasErrors(['resources']);
});

it('only lists the authenticated users own subscriptions', function () {
    $user = actingAsUser();
    WebhookSubscription::factory()->create(['user_id' => $user->id]);
    WebhookSubscription::factory()->create();

    Livewire::test('settings.webhooks')
        ->assertViewHas('subscriptions', fn ($subscriptions) => $subscriptions->count() === 1);
});

it('lets the owner edit url, resources and the reveal_secret flag', function () {
    $user = actingAsUser();
    $subscription = WebhookSubscription::factory()->create(['user_id' => $user->id]);

    Livewire::test('settings.webhooks')
        ->call('edit', $subscription->id)
        ->assertSet('editUrl', $subscription->url)
        ->set('editUrl', 'https://1.1.1.1/new-hook')
        ->set('editResources', ['meetup-event'])
        ->set('editRevealSecret', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($subscription->fresh())
        ->url->toBe('https://1.1.1.1/new-hook')
        ->resources->toBe(['meetup-event'])
        ->reveal_secret->toBeTrue();
});

it('shows the secret in the list once reveal_secret is on, and hides it once off again', function () {
    $user = actingAsUser();
    $subscription = WebhookSubscription::factory()->revealSecret()->create(['user_id' => $user->id]);

    Livewire::test('settings.webhooks')
        ->assertSee($subscription->secret);

    Livewire::test('settings.webhooks')
        ->call('edit', $subscription->id)
        ->set('editRevealSecret', false)
        ->call('save')
        ->assertDontSee($subscription->secret);
});

it('lets the owner pause and resume a subscription', function () {
    $user = actingAsUser();
    $subscription = WebhookSubscription::factory()->create(['user_id' => $user->id, 'active' => true]);

    Livewire::test('settings.webhooks')->call('toggleActive', $subscription->id);
    expect($subscription->fresh()->active)->toBeFalse();

    Livewire::test('settings.webhooks')->call('toggleActive', $subscription->id);
    expect($subscription->fresh()->active)->toBeTrue();
});

it('clears the disabled state when the owner resumes a system-disabled subscription', function () {
    $user = actingAsUser();
    $subscription = WebhookSubscription::factory()->disabled()->create(['user_id' => $user->id, 'active' => false]);

    Livewire::test('settings.webhooks')->call('toggleActive', $subscription->id);

    expect($subscription->fresh())
        ->active->toBeTrue()
        ->disabled_at->toBeNull()
        ->consecutive_failures->toBe(0);
});

it('lets the owner delete a subscription', function () {
    $user = actingAsUser();
    $subscription = WebhookSubscription::factory()->create(['user_id' => $user->id]);

    Livewire::test('settings.webhooks')->call('deleteSubscription', $subscription->id);

    expect(WebhookSubscription::query()->find($subscription->id))->toBeNull();
});

it('shows a rejected subscription as distinguishable from a pending one', function () {
    $user = actingAsUser();
    WebhookSubscription::factory()->rejected()->create(['user_id' => $user->id]);

    Livewire::test('settings.webhooks')
        ->assertSee('Abgelehnt')
        ->assertDontSee('Wartet auf Freigabe');
});

it('never lets another user edit, delete or see the secret of someones elses subscription', function () {
    $owner = User::factory()->create();
    $subscription = WebhookSubscription::factory()->revealSecret()->create(['user_id' => $owner->id]);

    actingAsUser();

    $component = Livewire::test('settings.webhooks');
    $component->assertDontSee($subscription->secret);

    expect(fn () => $component->call('edit', $subscription->id))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test('settings.webhooks')->call('toggleActive', $subscription->id))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test('settings.webhooks')->call('deleteSubscription', $subscription->id))
        ->toThrow(ModelNotFoundException::class);

    expect(WebhookSubscription::query()->find($subscription->id))
        ->not->toBeNull()
        ->reveal_secret->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Resource labels (P4 of the #36-#45 gap closure)
|--------------------------------------------------------------------------
|
| `webhooks.allowed_resources` holds database names. The picker used to print
| them raw, so a reader saw "meetup-event" — a word that appears nowhere else
| in the portal's UI — and saw it identically in all nine locales, because a
| raw slug never passes through __(). The checkbox VALUE stays the slug; only
| the label is product wording.
|
*/

/**
 * Only the text a reader sees — an assertion about a LABEL must not pass on
 * the checkbox's value attribute, which stays the API slug on purpose.
 */
function visibleText(string $html): string
{
    return html_entity_decode(strip_tags($html));
}

it('labels the resource checkboxes with product wording, not the config slugs', function () {
    actingAsUser();

    $html = Livewire::test('settings.webhooks')
        ->assertSee('Meetup-Termin')
        // The submitted value is still the API slug, not the label.
        ->assertSeeHtml('value="meetup-event"')
        ->html();

    expect(visibleText($html))->not->toContain('meetup-event');
});

it('labels the resources of an existing subscription with product wording', function () {
    $user = actingAsUser();
    WebhookSubscription::factory()->create([
        'user_id' => $user->id,
        'resources' => ['meetup', 'meetup-event'],
    ]);

    Livewire::test('settings.webhooks')
        ->assertSee('Meetup, Meetup-Termin')
        ->assertDontSee('meetup, meetup-event');
});

it('translates the resource labels instead of printing one string for all nine locales', function () {
    $user = actingAsUser();
    WebhookSubscription::factory()->create([
        'user_id' => $user->id,
        'resources' => ['meetup-event'],
    ]);

    // lang/*.json only gains the key in P6, so the label is injected here instead:
    // a raw slug could not follow the locale at all, a translated label must.
    app()->setLocale('en');
    Lang::addLines(['*.Meetup-Termin' => 'Meetup event'], 'en');

    $html = Livewire::test('settings.webhooks')
        ->assertSee('Meetup event')
        ->html();

    expect(visibleText($html))->not->toContain('meetup-event');
});

it('falls back to the raw slug for a resource that has no label yet', function () {
    actingAsUser();

    expect(Livewire::test('settings.webhooks')->instance()->resourceLabel('brand-new-resource'))
        ->toBe('brand-new-resource');
});
