<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\User;

/*
 * A long meetup name in the sidebar's "my meetups" entries ran past the
 * sidebar and was cut off without a mark ("Einundzwanzig Neumarkt in de",
 * 2026-10-25). It now shows without the brand prefix and ends in an ellipsis
 * inside the sidebar.
 */

it('ends a long led meetup name in an ellipsis inside the sidebar', function () {
    $country = portalCountry();
    $city = City::factory()->create(['country_id' => $country->id]);
    $leader = User::factory()->create();
    $meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'name' => 'Einundzwanzig Neumarkt in der Oberpfalz und Umgebung',
    ]);
    $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    $this->actingAs($leader);
    $page = visit('/de/services')->resize(1280, 900)->wait(1.0);

    $m = $page->script(<<<'JS'
        (() => {
          const item = document.querySelector('[data-testid="sidebar-my-meetup"]');
          const label = item.querySelector('[data-content]');
          const sidebar = item.closest('[data-flux-sidebar]') || item.closest('nav') || document.body;
          return {
            text: label.textContent.trim(),
            title: item.getAttribute('title'),
            truncated: label.scrollWidth > label.clientWidth,
            ellipsis: getComputedStyle(label).textOverflow,
            itemRight: Math.round(item.getBoundingClientRect().right),
            sidebarRight: Math.round(sidebar.getBoundingClientRect().right),
          };
        })()
    JS);

    expect($m['text'])->toBe('Neumarkt in der Oberpfalz und Umgebung')
        ->and($m['title'])->toBe('Einundzwanzig Neumarkt in der Oberpfalz und Umgebung')
        ->and($m['truncated'])->toBeTrue('not truncated: '.json_encode($m))
        ->and($m['ellipsis'])->toBe('ellipsis')
        ->and($m['itemRight'])->toBeLessThanOrEqual($m['sidebarRight'], json_encode($m));

    $page->assertNoJavaScriptErrors();
});
