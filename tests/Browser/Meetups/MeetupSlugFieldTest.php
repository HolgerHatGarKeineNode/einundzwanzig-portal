<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Issue #167 — the editable URL slug on meetups/edit
|--------------------------------------------------------------------------
|
| Two failures that no feature test can see, both measured while building it:
|
| 1. Livewire 4's `wire:model.blur` only updates the CLIENT copy of the property
|    on blur; it sends nothing. With it, leaving the field after typing
|    "Einundzwanzig Praha" left the raw text standing and the collision with an
|    existing meetup unreported until save. `Livewire::test()->set()` always
|    round-trips, so the feature tests were green throughout. `.live.blur` is
|    what makes the check happen as the user leaves the field.
|
| 2. A Flux button is `whitespace-nowrap`. With the current slug in the reset
|    button's label ("Zurücksetzen auf /meetup/testovaci-zaznam-z-nostr") the
|    field was wider than a 375px viewport and its text was clipped on the right,
|    while `documentElement.scrollWidth` still equalled clientWidth — no
|    scrollbar, the text simply gone (same signature as Issues #55 and #66). So
|    the right edge of every element in the field is measured, not the page.
|
*/

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'name' => 'Testovaci zaznam',
        'slug' => 'testovaci-zaznam-z-nostr',
        'community' => 'einundzwanzig',
        'created_by' => $this->creator->id,
    ]);
    Meetup::factory()->create(['city_id' => $city->id, 'name' => 'Einundzwanzig Praha', 'slug' => 'einundzwanzig-praha']);
    $this->actingAs($this->creator);
});

it('checks the slug as the field is left and keeps the field inside the viewport', function (int $viewport) {
    $field = '[data-testid=meetup-slug-field]';

    $page = visit('/de/meetup-edit/'.$this->meetup->id);
    $page->resize($viewport, 1400)->wait(2.5);

    $page->type("{$field} input", 'Einundzwanzig Praha');
    $page->keys("{$field} input", 'Tab');

    $page->assertSee('Diese Adresse gehört bereits zum Meetup Einundzwanzig Praha.')
        ->assertNoJavaScriptErrors();

    $measured = $page->script('(() => {
      const field = document.querySelector("[data-testid=meetup-slug-field]");
      const rights = [...field.querySelectorAll("*")].map(el => el.getBoundingClientRect().right);
      return {
        value: field.querySelector("input").value,
        rightmost: Math.max(...rights),
        viewport: document.documentElement.clientWidth,
      };
    })()');

    expect($measured['value'])->toBe('einundzwanzig-praha')
        ->and($measured['rightmost'])->toBeLessThanOrEqual($measured['viewport']);
})->with([375, 1280]);
