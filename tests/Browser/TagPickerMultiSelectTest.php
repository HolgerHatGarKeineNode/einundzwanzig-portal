<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Tag;
use Database\Seeders\TagSeeder;

/*
 * Mehrfachauswahl im Tag-Waehler — gemeldet am 2026-08-23: der Waehler nahm nur noch
 * einen Tag an und tauschte ihn bei jeder weiteren Wahl aus.
 *
 * Der Fall laesst sich NUR im Browser pruefen: Livewire::test() setzt die Property
 * direkt und geht damit an Flux' clientseitiger Pillbox vorbei, die den Wert
 * tatsaechlich zusammenbaut. Serverseitig war alles gruen, waehrend der Waehler im
 * Browser kaputt war.
 */
it('keeps both tags when two are picked in a row', function () {
    $this->seed(TagSeeder::class);

    $user = actingAsUser();
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $user->id]);

    // Nur hervorgehobene Marken sind ohne Tippen sichtbar (CSS im Waehler).
    $featured = Tag::query()->where('type', 'meetup_event')->where('featured', true)->take(2)->get();

    expect($featured)->toHaveCount(2);

    $page = visit("/de/meetup/{$meetup->id}/events/create");

    $page->assertNoJavaScriptErrors()
        ->click('[data-testid="tag-picker"]')
        ->click('[data-testid="tag-option-'.$featured[0]->id.'"]')
        ->wait(1)
        // Flux schliesst die Liste nach jeder Wahl — erneut oeffnen, wie ein Nutzer es tut.
        ->click('[data-testid="tag-picker"]')
        ->click('[data-testid="tag-option-'.$featured[1]->id.'"]')
        ->wait(1)
        ->assertSee($featured[0]->displayName())
        ->assertSee($featured[1]->displayName());
});
