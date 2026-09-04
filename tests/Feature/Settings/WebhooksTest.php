<?php

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        // The submitted value is still the API slug, not the label.
        ->assertSeeHtml('value="meetup-event"')
        ->html();

    // Deliberately NOT assertSee('Meetup-Termin'): the page subheading says
    // "sobald sich ein Meetup oder ein Meetup-Termin ändert", so the string
    // occurs three times in this HTML and the assertion could not fail even if
    // the checkbox were labelled with the raw slug (measured 2026-09-04). What
    // this test claims is that THIS checkbox carries THAT label, so the
    // assertion has to tie the two together on one element.
    expect($html)->toMatch('/<ui-checkbox[^>]*value="meetup-event"[^>]*label="Meetup-Termin"/')
        ->and(visibleText($html))->not->toContain('meetup-event');
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

    // The injected label this test used to carry is gone: lang/en.json now holds
    // the key for real ("Meetup events"), so Lang::addLines() no longer changed
    // anything, and assertSee('Meetup event') passed on the plural regardless of
    // whether the injection worked (measured 2026-09-04). The claim is that the
    // label FOLLOWS the locale, so the German label has to be gone from the page.
    app()->setLocale('en');

    $html = Livewire::test('settings.webhooks')->html();

    expect(visibleText($html))
        ->toContain(__('Meetup-Termin'))
        ->not->toContain('Meetup-Termin')
        ->not->toContain('meetup-event');
});

it('falls back to the raw slug for a resource that has no label yet', function () {
    actingAsUser();

    expect(Livewire::test('settings.webhooks')->instance()->resourceLabel('brand-new-resource'))
        ->toBe('brand-new-resource');
});

/*
|--------------------------------------------------------------------------
| Issue #54, item 1 — the "and then?" contact block
|--------------------------------------------------------------------------
|
| The page named who approves but not what to do while nothing happens. It
| now says so and hands over an address. Same shape as ServiceDisclaimerTest:
| assert the njump link where it belongs, and its ABSENCE in the negative
| case, because that is the half a rendering bug actually breaks.
|
| The npub lives in config/einundzwanzig.php (webhooks.contact_npub) so this
| page and /docs/webhooks cannot drift apart; both tests read it from there
| rather than hardcoding it, so a rotated key does not turn into a red suite.
|
*/

it('shows the contact npub, its copy control and its njump link', function () {
    actingAsUser();

    $npub = config('einundzwanzig.webhooks.contact_npub');

    // Guard on the fixture itself: with an empty key the assertions below would
    // be measuring the negative case while claiming to measure the positive one.
    expect($npub)->toBeString()->not->toBe('');

    $html = Livewire::test('settings.webhooks')
        ->assertSee('frag per Nostr-DM nach')
        ->assertSee($npub)
        ->assertSeeHtml('https://njump.me/'.$npub)
        ->html();

    // The address IS the copy control — clicking the npub copies the npub, which
    // is why there is no separate copy button to look for.
    expect($html)->toMatch('/<button[^>]*data-testid="webhook-contact-npub"[^>]*x-copy-to-clipboard/');
});

it('drops the whole ask-by-DM sentence when no contact npub is configured', function () {
    actingAsUser();

    config()->set('einundzwanzig.webhooks.contact_npub', '');

    $html = Livewire::test('settings.webhooks')
        // The sentence and the address stand or fall together: on its own the
        // sentence ends in a colon and promises an address that never arrives,
        // which is worse than the gap this issue started from.
        ->assertDontSee('frag per Nostr-DM nach')
        ->assertDontSee('njump.me')
        ->html();

    expect($html)->not->toContain('webhook-contact-npub')
        // Nothing left over that ends in a dangling colon.
        ->and(visibleText($html))->not->toContain('Nostr-DM');
});
